<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\MessageDispatcher;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Seluruh siklus penagihan dalam satu lintasan harian.
 *
 * Sengaja satu job, bukan empat yang terjadwal terpisah. Urutan langkahnya
 * saling bergantung — tagihan harus terbit sebelum pengingatnya dikirim, dan
 * langganan harus dinyatakan lewat jatuh tempo sebelum masa tenggangnya bisa
 * dihitung — dan empat jadwal terpisah berarti empat kesempatan bagi urutan itu
 * untuk kacau saat salah satunya gagal atau tertunda di antrean.
 *
 * Aman dijalankan berkali-kali dalam sehari: setiap langkah memeriksa keadaan
 * sebelum mengubahnya, dan pengingat dijaga penanda di cache.
 */
class BillingCycleJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function handle(SubscriptionService $subscriptions, MessageDispatcher $dispatcher): void
    {
        $this->expireOverdueInvoices();
        $this->issueRenewalInvoices($subscriptions);
        $this->sendReminders($dispatcher);
        $this->markPastDue($subscriptions);
        $this->suspendAfterGrace($subscriptions);
    }

    /**
     * Tagihan yang lewat batas bayar ditutup, supaya kode uniknya bebas dipakai
     * tagihan berikutnya dan pelanggan tidak membayar nominal yang sudah tidak
     * dicari siapa-siapa.
     */
    private function expireOverdueInvoices(): void
    {
        Invoice::where('status', 'pending')
            ->where('due_at', '<', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    /**
     * Menerbitkan tagihan perpanjangan beberapa hari sebelum periode berakhir.
     *
     * Jaraknya penting: VA dan gerai ritel butuh waktu, dan tagihan yang baru
     * terbit di hari jatuh tempo berarti setiap pelanggan mengalami layanan
     * berhenti minimal sekali sebelum sempat membayar.
     */
    private function issueRenewalInvoices(SubscriptionService $subscriptions): void
    {
        $batas = now()->addDays(config('billing.issue_days_before'))->endOfDay();

        Subscription::with('workspace')
            ->whereIn('status', ['trialing', 'active'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $batas)
            ->chunkById(100, function ($langganan) use ($subscriptions): void {
                foreach ($langganan as $subscription) {
                    $workspace = $subscription->workspace;

                    if (! $workspace || $workspace->is_internal) {
                        continue;
                    }

                    // Sudah ada tagihan terbuka: menerbitkan yang kedua berarti
                    // dua nominal berbeda beredar untuk satu perpanjangan.
                    $adaTerbuka = $workspace->invoices()
                        ->where('status', 'pending')
                        ->where('due_at', '>', now())
                        ->exists();

                    if ($adaTerbuka) {
                        continue;
                    }

                    try {
                        $subscriptions->issueInvoice(
                            $workspace,
                            $subscription->plan_slug,
                            $subscription->period,
                        );
                    } catch (\Throwable $e) {
                        // Satu workspace yang gagal tidak boleh menghentikan
                        // penerbitan tagihan seluruh pelanggan lain.
                        Log::error('Gagal menerbitkan tagihan perpanjangan.', [
                            'workspace_id' => $workspace->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    /**
     * Pengingat lewat WhatsApp, memakai gateway kami sendiri.
     *
     * Seluruhnya pelengkap. Spanduk di dashboard dan tagihan yang sudah terbit
     * adalah pemberitahuan yang sebenarnya; kegagalan di sini tidak boleh
     * mengubah satu pun status langganan.
     */
    private function sendReminders(MessageDispatcher $dispatcher): void
    {
        $pengirim = $this->reminderSession();

        if (! $pengirim) {
            return;
        }

        foreach (config('billing.reminder_days') as $sisaHari) {
            $tanggal = now()->addDays($sisaHari);

            Subscription::with('workspace')
                ->whereIn('status', ['trialing', 'active'])
                ->whereBetween('current_period_end', [$tanggal->copy()->startOfDay(), $tanggal->copy()->endOfDay()])
                ->get()
                ->each(function (Subscription $subscription) use ($dispatcher, $pengirim, $sisaHari): void {
                    $workspace = $subscription->workspace;

                    if (! $workspace || $workspace->is_internal || blank($workspace->billing_phone)) {
                        return;
                    }

                    $penanda = "billing:reminder:{$subscription->id}:{$sisaHari}";

                    if (Cache::has($penanda)) {
                        return;
                    }

                    try {
                        $dispatcher->queue($pengirim, $workspace->billing_phone, [
                            'body' => $this->reminderText($subscription, $sisaHari),
                        ]);

                        Cache::put($penanda, true, now()->addDay());
                    } catch (\Throwable $e) {
                        Log::warning('Pengingat tagihan gagal dikirim.', [
                            'workspace_id' => $workspace->id,
                            'nomor' => PhoneNumber::mask($workspace->billing_phone),
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
        }
    }

    private function reminderText(Subscription $subscription, int $sisaHari): string
    {
        $nama = $subscription->workspace->name;
        $paket = $subscription->plan()->name();
        $tanggal = $subscription->current_period_end->translatedFormat('j F Y');
        $url = route('billing.index');

        $pembuka = match (true) {
            $sisaHari <= 0 => "Langganan {$paket} untuk workspace *{$nama}* berakhir hari ini ({$tanggal}).",
            $sisaHari === 1 => "Langganan {$paket} untuk workspace *{$nama}* berakhir besok ({$tanggal}).",
            default => "Langganan {$paket} untuk workspace *{$nama}* berakhir {$sisaHari} hari lagi, pada {$tanggal}.",
        };

        return $pembuka."\n\n"
            .'Setelah tanggal itu pengiriman pesan berhenti, tapi nomor WhatsApp Anda tetap tertaut dan tidak perlu discan ulang selama '
            .config('billing.grace_days')." hari.\n\n"
            ."Perpanjang di: {$url}";
    }

    /**
     * Sesi milik Flustra sendiri yang dipakai mengirim pengingat.
     *
     * Dikonfigurasi lewat env dan bukan dipilih otomatis: mengirim pemberitahuan
     * tagihan dari nomor pelanggan mana pun yang kebetulan tersambung berarti
     * pelanggan itu yang membayar kuotanya dan yang menerima laporan spam-nya.
     */
    private function reminderSession(): ?WaSession
    {
        $workspaceId = config('billing.notify_workspace_id');

        if (blank($workspaceId)) {
            return null;
        }

        return Workspace::find($workspaceId)
            ?->sessions()
            ->where('status', 'connected')
            ->first();
    }

    /**
     * Periode habis tanpa pembayaran: pengiriman berhenti, nomor tetap tertaut.
     */
    private function markPastDue(SubscriptionService $subscriptions): void
    {
        Subscription::with('workspace')
            ->whereIn('status', ['trialing', 'active'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->chunkById(100, function ($langganan) use ($subscriptions): void {
                foreach ($langganan as $subscription) {
                    if ($subscription->workspace?->is_internal) {
                        continue;
                    }

                    $subscriptions->markPastDue($subscription);
                }
            });
    }

    /**
     * Masa tenggang habis: sesi dilepas dari engine.
     *
     * Ini satu-satunya langkah yang benar-benar membebaskan RAM, dan karena itu
     * satu-satunya yang punya nilai operasional langsung — sebuah sesi yang
     * tidak dibayar tetap memakan 300-500 MB yang dibutuhkan pelanggan berbayar.
     */
    private function suspendAfterGrace(SubscriptionService $subscriptions): void
    {
        $batas = now()->subDays(config('billing.grace_days'));

        Subscription::with('workspace.sessions')
            ->where('status', 'past_due')
            ->whereNotNull('past_due_at')
            ->where('past_due_at', '<', $batas)
            ->chunkById(50, function ($langganan) use ($subscriptions): void {
                foreach ($langganan as $subscription) {
                    if ($subscription->workspace?->is_internal) {
                        continue;
                    }

                    $subscriptions->suspend($subscription);
                }
            });
    }
}
