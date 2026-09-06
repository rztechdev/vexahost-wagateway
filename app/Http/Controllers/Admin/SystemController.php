<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HeaderKeamanan;
use App\Models\Message;
use App\Models\WaSession;
use App\Services\Billing\BalanceService;
use App\Services\Billing\QrisManual;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\KesehatanAntrean;
use App\Support\Plan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
        private readonly EmailNotifier $email,
        private readonly QrisManual $qris,
        private readonly BalanceService $balances,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('admin.system', [
            'engine' => $this->engine(),

            'keamanan' => $this->keamanan($request),

            // Saldo yang tidak cocok dengan buku besarnya adalah uang pelanggan
            // yang tidak bisa dipertanggungjawabkan — dan ia tidak menghasilkan
            // galat apa pun. Satu-satunya cara menyadarinya adalah melihatnya
            // tertulis di sini.
            'saldo' => $this->balances->workspaceTidakCocok(),

            'email' => [
                'siap' => $this->email->ready(),
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'dari' => config('mail.from.address'),
            ],

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

            'antrean' => KesehatanAntrean::periksa()
                + ['ambang' => KesehatanAntrean::ambangMenit()],

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
     * Empat hal yang membuat sambungan ke aplikasi ini aman — atau tidak.
     *
     * Ketiganya di luar `engine()` punya sifat yang sama dan itulah alasan
     * mereka ada di halaman ini: tidak satu pun menghasilkan galat saat salah.
     * Cookie yang bocor lewat http, halaman yang bisa dibingkai orang lain, dan
     * halaman galat yang memamerkan kata sandi database semuanya tampak persis
     * seperti aplikasi yang berjalan normal — sampai ada yang memanfaatkannya.
     *
     * `secure()` dibaca dari permintaan yang sedang berjalan, bukan dari
     * `APP_URL`: yang menentukan aman-tidaknya adalah sambungan yang benar-benar
     * dipakai admin saat ini, dan `APP_URL` boleh saja berbunyi https sementara
     * halamannya dibuka lewat http.
     *
     * @return array<string, bool|string>
     */
    private function keamanan(Request $request): array
    {
        return [
            // HTTPS dan cookie secure SENGAJA mati di lokal — dev berjalan di
            // http://127.0.0.1:8070. Menandainya merah tiap hari di mesin
            // pengembang melatih orang mengabaikan warna merah di halaman ini,
            // dan halaman ini cuma berguna selama merahnya masih berarti.
            'wajib_aman' => ! app()->environment(['local', 'testing']),
            'https' => $request->secure(),
            'cookie_secure' => (bool) config('session.secure'),
            'sesi_terenkripsi' => (bool) config('session.encrypt'),
            'debug_mati' => ! config('app.debug'),
            'header' => app(Kernel::class)->hasMiddleware(HeaderKeamanan::class),
        ];
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
