<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Notifications\BillingMessages;
use App\Services\Notifications\WhatsAppNotifier;
use App\Services\SessionService;
use App\Support\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya tempat status langganan berpindah.
 *
 * Setiap perpindahan menyentuh dua tabel sekaligus — `subscriptions.status`
 * yang dibaca halaman tagihan, dan `workspaces.status` yang dibaca
 * `MessageDispatcher` serta middleware API. Keduanya harus selalu sepakat:
 * langganan yang lewat jatuh tempo tapi workspace-nya masih `active` berarti
 * pelanggan tetap bisa mengirim tanpa membayar, dan sebaliknya berarti layanan
 * mati padahal tagihannya lunas.
 *
 * Karena itu tidak ada satu pun controller, job, atau perintah yang boleh
 * menulis `subscriptions.status` sendiri.
 */
class SubscriptionService
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly WhatsAppNotifier $notifier,
    ) {}

    /**
     * Langganan workspace, dibuatkan kalau belum ada.
     *
     * Workspace baru mulai sebagai `unpaid`: belum pernah berlangganan, belum
     * boleh mengirim apa pun. Ia harus memilih paket dan membayar lebih dulu.
     *
     * Masa gratis sampai akhir bulan **hanya** milik akun yang sudah ada
     * sebelum produk ini dijual, dan itu diberikan sekali oleh migrasi
     * `give_existing_workspaces_a_trial` — bukan oleh keadaan bawaan di sini.
     * Kalau bawaannya `trialing`, setiap pendaftar baru ikut memakai produk
     * berbayar secara cuma-cuma tanpa pernah diputuskan siapa pun.
     *
     * Kalau suatu saat masa coba untuk pendaftar baru memang diinginkan,
     * tempatnya di sini — dengan tanggal berakhir yang eksplisit, bukan dengan
     * mengembalikan `trialing` sebagai bawaan.
     */
    public function ensureFor(Workspace $workspace): Subscription
    {
        if ($workspace->subscription) {
            return $workspace->subscription;
        }

        $gratis = Plan::free();

        /*
         | Jatah coba gratis diberikan sekali per PEMILIK, bukan per workspace.
         |
         | Tidak ada batas berapa workspace yang boleh dibuat satu akun — dan
         | itu memang disengaja, satu orang dengan tiga cabang membuat tiga
         | workspace dan membayar tiga kali. Tapi kalau tiap workspace baru ikut
         | membawa masa cobanya sendiri, akun yang sama tinggal menekan
         | "Workspace Baru" sepuluh kali untuk mendapat sepuluh kali lima pesan
         | gratis — masing-masing menahan satu sesi WhatsApp, dari tiga yang
         | tersedia untuk SELURUH pelanggan. Kapasitas engine habis oleh orang
         | yang belum membayar sepeser pun, dan yang tertahan di luar justru
         | pelanggan berbayar.
         |
         | Workspace kedua dan seterusnya lahir `unpaid`: terlihat penuh, bisa
         | disiapkan, tapi baru hidup setelah tagihannya lunas.
        */
        $sudahPernahCoba = Workspace::query()
            ->where('owner_id', $workspace->owner_id)
            ->whereKeyNot($workspace->getKey())
            ->whereHas('subscription', fn ($q) => $q->where('plan_slug', $gratis->slug))
            ->exists();

        if ($sudahPernahCoba) {
            $subscription = $workspace->subscription()->create([
                'plan_slug' => config('plans.default'),
                'period' => 'monthly',
                'status' => 'unpaid',
                'current_period_start' => null,
                'current_period_end' => null,
            ]);

            $workspace->forceFill([
                'status' => 'suspended',
                'service_until' => null,
            ])->save();

            return $workspace->setRelation('subscription', $subscription)->subscription;
        }

        $subscription = $workspace->subscription()->create([
            'plan_slug' => $gratis->slug,
            'period' => 'monthly',
            'status' => 'trialing',
            'current_period_start' => now(),
            'current_period_end' => null,
        ]);

        /*
         | Workspace-nya HIDUP, dan itu memang maunya: pendaftar baru boleh
         | menautkan nomor, membuat API key, dan menguji integrasinya. Yang
         | membatasi bukan status melainkan jatah lima pesan — dihitung seumur
         | hidup workspace di `Workspace::freeMessagesUsed()`, bukan per bulan.
         |
         | `service_until` sengaja dibiarkan kosong: masa coba ini tidak punya
         | tanggal berakhir, ia berakhir saat pesan kelima terkirim.
        */
        $this->applyPlanLimits($workspace, $gratis);

        $workspace->forceFill([
            'status' => 'active',
            'service_until' => null,
        ])->save();

        return $workspace->setRelation('subscription', $subscription)->subscription;
    }

    /**
     * Menerbitkan tagihan. Mengembalikan tagihan yang sudah ada kalau workspace
     * masih punya satu yang menunggu bayar untuk paket dan periode yang sama —
     * mengklik "Bayar" dua kali tidak boleh menghasilkan dua nomor tagihan,
     * karena keduanya akan punya kode unik berbeda dan pelanggan akan membayar
     * salah satunya sementara yang lain menggantung sampai kedaluwarsa.
     */
    public function issueInvoice(Workspace $workspace, string $planSlug, string $period): Invoice
    {
        if (! Plan::exists($planSlug)) {
            throw new RuntimeException("Paket '{$planSlug}' tidak ada.");
        }

        if (! in_array($period, ['monthly', 'yearly'], true)) {
            throw new RuntimeException("Periode '{$period}' tidak dikenali.");
        }

        $subscription = $this->ensureFor($workspace);

        $existing = $workspace->invoices()
            ->where('status', 'pending')
            ->where('plan_slug', $planSlug)
            ->where('period', $period)
            ->where('due_at', '>', now())
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $plan = Plan::get($planSlug);
        $amount = $plan->price($period);
        $tax = (int) round($amount * config('billing.tax_percent') / 100);

        return DB::transaction(function () use ($workspace, $subscription, $planSlug, $period, $amount, $tax) {
            $code = $this->allocateUniqueCode($amount + $tax);

            $invoice = Invoice::create([
                // Diisi sementara lalu ditimpa: kolomnya unik dan wajib, tapi
                // nomor final diturunkan dari id yang baru ada setelah insert.
                'number' => 'INV-WA-'.Str::ulid(),
                'external_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'plan_slug' => $planSlug,
                'period' => $period,
                'amount' => $amount,
                'tax_amount' => $tax,
                'unique_code' => $code,
                'total' => $amount + $tax + $code,
                'status' => 'pending',
                'channel' => 'qris_manual',
                'due_at' => now()->addDays(config('billing.invoice_due_days')),
            ]);

            $invoice->forceFill([
                'number' => 'INV-WA-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            // Id workspace disebut eksplisit: penerbitan tagihan juga terjadi
            // dari job harian, dan di sana tidak ada sesi yang bisa ditanya
            // workspace mana yang sedang dibuka.
            AuditLog::record('invoice.issued', $invoice, [
                'plan' => $planSlug,
                'period' => $period,
                'total' => $invoice->total,
            ], $workspace->id);

            return $invoice;
        });
    }

    /**
     * Menandai tagihan lunas dan memperpanjang langganan.
     *
     * `$admin` adalah orang yang menyetujui pembayaran. Selama pembayaran masih
     * dicocokkan manual, kolom itu satu-satunya jejak siapa yang memutuskan
     * uangnya benar masuk. Saat gateway otomatis nanti masuk, ia dibiarkan
     * kosong dan `note` yang menyebut referensi dari penyedia.
     */
    public function markPaid(Invoice $invoice, ?User $admin = null, ?string $note = null): Invoice
    {
        if ($invoice->isPaid()) {
            return $invoice;
        }

        $invoice = DB::transaction(function () use ($invoice, $admin, $note) {
            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_user_id' => $admin?->id,
                'note' => $note,
            ])->save();

            $workspace = $invoice->workspace;
            $subscription = $invoice->subscription ?? $this->ensureFor($workspace);

            /*
             | Perpanjangan dihitung dari akhir periode berjalan kalau periode
             | itu belum lewat. Pelanggan yang membayar tiga hari lebih awal
             | tidak boleh kehilangan tiga hari yang sudah dibayarnya bulan lalu.
             |
             | Kalau sudah lewat, hitungan dimulai dari sekarang — bukan dari
             | akhir periode lama. Menyambung dari tanggal lampau berarti
             | pelanggan yang telat sebulan membayar penuh untuk periode yang
             | seluruhnya sudah berlalu, dan layanannya kembali mati seketika.
             */
            $mulai = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end->copy()
                : now();

            $subscription->forceFill([
                'plan_slug' => $invoice->plan_slug,
                'period' => $invoice->period,
                'status' => 'active',
                'current_period_start' => $mulai,
                'current_period_end' => $mulai->copy()->addMonths($invoice->periodMonths()),
                'past_due_at' => null,
                'suspended_at' => null,
                'canceled_at' => null,
            ])->save();

            $this->applyPlanLimits($workspace, $subscription->plan());

            // Tanggalnya ikut turun ke baris workspace supaya penolakan setelah
            // masa berlaku habis terjadi seketika, bukan menunggu job pukul 08:00.
            $workspace->forceFill([
                'status' => 'active',
                'service_until' => $subscription->current_period_end,
            ])->save();

            AuditLog::record('invoice.paid', $invoice, [
                'plan' => $invoice->plan_slug,
                'total' => $invoice->total,
                'oleh' => $admin?->email,
            ], $workspace->id);

            return $invoice->refresh();
        });

        /*
         | Dikirim di luar transaksi, dengan sengaja.
         |
         | Di dalamnya, pesan yang gagal akan menggulung balik penandaan lunas —
         | pembayaran yang sudah masuk batal tercatat gara-gara WhatsApp sedang
         | tersendat. Di luar, kegagalannya tinggal satu baris log.
        */
        $this->notifier->toWorkspace(
            $invoice->workspace,
            BillingMessages::paymentConfirmed($invoice, $invoice->workspace->subscription),
            "invoice-paid:{$invoice->id}",
        );

        return $invoice;
    }

    /**
     * Periode habis tanpa pembayaran: pengiriman berhenti, nomor tetap tertaut.
     *
     * Workspace ditandai `suspended` supaya seluruh penegakan yang sudah ada —
     * `MessageDispatcher::guardWorkspace()` dan `AuthenticateApiKey` — ikut
     * berlaku tanpa perlu satu pun pemeriksaan tambahan di dalamnya.
     */
    public function markPastDue(Subscription $subscription): void
    {
        if ($subscription->status === 'past_due') {
            return;
        }

        DB::transaction(function () use ($subscription) {
            $subscription->forceFill([
                'status' => 'past_due',
                'past_due_at' => now(),
            ])->save();

            $subscription->workspace->forceFill(['status' => 'suspended'])->save();

            AuditLog::record('subscription.past_due', $subscription, [
                'workspace' => $subscription->workspace->name,
            ], $subscription->workspace_id);
        });

        $this->notifier->toWorkspace(
            $subscription->workspace,
            BillingMessages::serviceStopped($subscription),
            "past-due:{$subscription->id}",
            24 * 30,
        );
    }

    /**
     * Masa tenggang habis: sesi dilepas dari engine supaya Chromium-nya berhenti
     * memakan RAM yang dibutuhkan pelanggan yang membayar.
     *
     * Memakai `disconnect()`, bukan `logout()`. Bedanya menentukan: logout
     * membuang kredensial di kedua sisi, sehingga pelanggan yang kembali
     * membayar harus scan QR ulang — dan itu alasan paling sering orang tidak
     * jadi kembali. Disconnect hanya menghentikan prosesnya; cadangan sesi
     * tetap tersimpan dan nomornya menyala lagi sendiri saat dipulihkan.
     */
    public function suspend(Subscription $subscription): void
    {
        if ($subscription->status === 'suspended') {
            return;
        }

        $subscription->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $subscription->workspace->forceFill(['status' => 'suspended'])->save();

        foreach ($subscription->workspace->sessions as $session) {
            if ($session->status === 'disconnected') {
                continue;
            }

            try {
                $this->sessions->disconnect($session);
            } catch (\Throwable $e) {
                // Engine sedang tidak terjangkau. Langganan tetap ditangguhkan;
                // sesi yang tertinggal hidup akan ikut mati saat engine di-deploy
                // ulang, dan pengirimannya sudah diblokir di sisi Laravel.
                Log::warning('Gagal memutus sesi saat menangguhkan langganan.', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLog::record('subscription.suspended', $subscription, [
            'workspace' => $subscription->workspace->name,
        ], $subscription->workspace_id);

        $this->notifier->toWorkspace(
            $subscription->workspace,
            BillingMessages::sessionsReleased($subscription),
            "suspended:{$subscription->id}",
            24 * 30,
        );
    }

    /**
     * Memberi kelonggaran tanpa pembayaran — untuk pelanggan yang uangnya sudah
     * ditransfer tapi buktinya belum sampai, dan untuk memperpanjang percobaan.
     */
    public function extend(Subscription $subscription, int $days, ?User $admin = null): void
    {
        $dari = $subscription->current_period_end && $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end->copy()
            : now();

        $subscription->forceFill([
            'status' => $subscription->status === 'canceled' ? 'canceled' : 'active',
            'current_period_end' => $dari->addDays($days),
            'past_due_at' => null,
            'suspended_at' => null,
        ])->save();

        $subscription->workspace->forceFill([
            'status' => 'active',
            'service_until' => $subscription->current_period_end,
        ])->save();

        AuditLog::record('subscription.extended', $subscription, [
            'hari' => $days,
            'oleh' => $admin?->email,
        ], $subscription->workspace_id);
    }

    /**
     * Menyalin batas paket ke kolom workspace.
     *
     * Batas ditegakkan dari kolom, bukan dibaca dari paket setiap kali, karena
     * dua penegakan yang paling panas — kuota pesan dan jumlah sesi — berjalan
     * di jalur pengiriman dan tidak boleh memuat config per pesan. Kolom ini
     * juga yang memberi admin ruang menaikkan satu workspace tanpa memindahkan
     * seluruh pelanggan paket itu.
     */
    public function applyPlanLimits(Workspace $workspace, Plan $plan): void
    {
        $workspace->forceFill($plan->limits() + ['plan_slug' => $plan->slug])->save();
    }

    /**
     * Kode unik yang belum dipakai tagihan lain yang masih menunggu bayar
     * dengan nominal dasar sama.
     *
     * Yang dijaga adalah keunikan NOMINAL AKHIR di antara tagihan terbuka —
     * itulah satu-satunya yang terbaca di mutasi rekening. Dua tagihan lunas
     * boleh berbagi kode; keduanya sudah tidak perlu dicocokkan lagi.
     */
    private function allocateUniqueCode(int $baseAmount): int
    {
        if (! config('billing.unique_code.enabled')) {
            return 0;
        }

        $min = (int) config('billing.unique_code.min');
        $max = (int) config('billing.unique_code.max');

        $terpakai = Invoice::where('status', 'pending')
            ->where('due_at', '>', now())
            ->whereRaw('amount + tax_amount = ?', [$baseAmount])
            ->pluck('unique_code')
            ->all();

        $tersedia = array_values(array_diff(range($min, $max), $terpakai));

        // Seluruh 999 kode terpakai untuk satu nominal berarti ada 999 tagihan
        // terbuka pada paket yang sama. Jauh sebelum itu terjadi, pembayaran
        // sudah harus otomatis — tapi tagihan tetap harus bisa terbit.
        if ($tersedia === []) {
            return 0;
        }

        return $tersedia[random_int(0, count($tersedia) - 1)];
    }
}
