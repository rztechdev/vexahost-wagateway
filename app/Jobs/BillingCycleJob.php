<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Services\Notifications\BillingMessages;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    public function handle(SubscriptionService $subscriptions, WhatsAppNotifier $notifier): void
    {
        $this->expireOverdueInvoices();
        $this->issueRenewalInvoices($subscriptions);
        $this->sendReminders($notifier);
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
            /*
             | Yang sudah ada buktinya dikecualikan.
             |
             | Uangnya sudah dikirim; yang belum selesai adalah pemeriksaan di
             | pihak kami. Menandainya kedaluwarsa berarti menghukum pelanggan
             | karena kami lambat memverifikasi — ia membuka halaman tagihannya
             | dan membaca "batas waktu pembayaran sudah lewat" padahal sudah
             | membayar. Tagihan seperti ini tetap terbuka sampai ada manusia
             | yang menjawabnya, dan panel admin memang menampilkannya paling
             | atas justru karena itu.
            */
            ->whereNull('proof_path')
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

                    if (! $workspace || $workspace->isExempt()) {
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
     * Pengingat sebelum masa berlaku habis.
     *
     * Seluruhnya pelengkap: spanduk di dashboard dan tagihan yang sudah terbit
     * adalah pemberitahuan yang sebenarnya, dan kegagalan di sini tidak boleh
     * mengubah satu pun status langganan. Tapi pelanggan yang gateway-nya
     * berjalan lancar justru yang paling jarang membuka dashboard — jadi di
     * praktiknya, inilah satu-satunya yang benar-benar sampai.
     */
    private function sendReminders(WhatsAppNotifier $notifier): void
    {
        foreach (config('billing.reminder_days') as $sisaHari) {
            $tanggal = now()->addDays($sisaHari);

            Subscription::with('workspace')
                ->whereIn('status', ['trialing', 'active'])
                ->whereBetween('current_period_end', [$tanggal->copy()->startOfDay(), $tanggal->copy()->endOfDay()])
                ->get()
                ->each(function (Subscription $subscription) use ($notifier, $sisaHari): void {
                    if (! $subscription->workspace) {
                        return;
                    }

                    $notifier->toWorkspace(
                        $subscription->workspace,
                        BillingMessages::expiringSoon($subscription, $sisaHari),
                        "reminder:{$subscription->id}:{$sisaHari}",
                    );
                });
        }
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
                    if ($subscription->workspace?->isExempt()) {
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
                    if ($subscription->workspace?->isExempt()) {
                        continue;
                    }

                    $subscriptions->suspend($subscription);
                }
            });
    }
}
