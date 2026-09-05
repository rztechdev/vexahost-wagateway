<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\WaSession;
use App\Services\Billing\QrisManual;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Kesehatan sistem dan konfigurasi yang sedang berlaku.
 *
 * Halaman ini menjawab satu pertanyaan: **apa yang sebenarnya sedang menyala?**
 *
 * Hampir semua kegagalan mahal di sistem ini punya bentuk yang sama — sesuatu
 * berhenti bekerja tanpa satu pun gejala. Engine mati dan sesi tampak masih
 * hijau. `BILLING_NOTIFY_WORKSPACE_ID` kosong dan seluruh pemberitahuan diam.
 * QRIS salah salin dan halaman bayar diam-diam jatuh ke transfer bank. Ketiganya
 * tidak melempar galat ke siapa pun; satu-satunya cara menyadarinya adalah
 * dengan melihatnya tertulis.
 */
class SystemController extends Controller
{
    public function __construct(
        private readonly WhatsAppNotifier $notifier,
        private readonly QrisManual $qris,
    ) {}

    public function __invoke(): View
    {
        return view('admin.system', [
            'engine' => $this->engine(),

            'notifikasi' => [
                'siap' => $this->notifier->ready(),
                'workspace' => config('billing.notify_workspace_id'),
                'nomorAdmin' => config('billing.admin_phone'),
            ],

            'pembayaran' => [
                'qris' => $this->qris->available(),
                'merchant' => $this->qris->merchantName(),
                'bank' => $this->qris->bankAccount(),
                'pajak' => (float) config('billing.tax_percent'),
            ],

            'antrean' => [
                'menunggu' => DB::table('jobs')->count(),
                'gagal' => DB::table('failed_jobs')->count(),
                'tertua' => optional(DB::table('jobs')->orderBy('id')->first())->available_at,
            ],

            'kapasitas' => [
                'hidup' => WaSession::whereIn('status', ['connected', 'connecting', 'qr'])->count(),
                'batas' => (int) config('gateway.engine.max_sessions'),
            ],

            'siklus' => [
                'jeda_kirim' => config('gateway.throttle.min_delay_ms').'–'.config('gateway.throttle.max_delay_ms').' ms',
                'tenggat_tagihan' => config('billing.invoice_due_days'),
                'terbit_sebelum' => config('billing.issue_days_before'),
                'masa_tenggang' => config('billing.grace_days'),
                'pengingat' => implode(', ', config('billing.reminder_days')),
            ],

            'paket' => Plan::all(),

            'pesanGagal24Jam' => Message::where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),

            'lingkungan' => [
                'app_env' => app()->environment(),
                'app_debug' => (bool) config('app.debug'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'zona_waktu' => config('app.timezone'),
            ],
        ]);
    }

    /**
     * Keadaan engine, ditanyakan saat itu juga.
     *
     * Ditanyakan langsung dan bukan dibaca dari database dengan sengaja: baris
     * di database hanya seakurat callback terakhir yang berhasil sampai, dan
     * engine yang mati mendadak tidak sempat mengirim apa pun. Justru keadaan
     * itulah yang paling perlu terlihat di halaman ini.
     *
     * @return array{terjangkau: bool, pesan: string}
     */
    private function engine(): array
    {
        try {
            $jawaban = Http::timeout((int) config('gateway.engine.connect_timeout', 5))
                ->withToken(config('gateway.engine.token'))
                ->get(rtrim(config('gateway.engine.url'), '/').'/health');

            return $jawaban->successful()
                ? ['terjangkau' => true, 'pesan' => 'Menjawab normal.']
                : ['terjangkau' => false, 'pesan' => 'Menjawab dengan status '.$jawaban->status().'.'];
        } catch (\Throwable $e) {
            return ['terjangkau' => false, 'pesan' => $e->getMessage()];
        }
    }
}
