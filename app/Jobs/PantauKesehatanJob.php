<?php

namespace App\Jobs;

use App\Services\Billing\BalanceService;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\KesehatanAntrean;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Mengubah pemeriksaan halaman Sistem menjadi kabar yang mengetuk sendiri.
 *
 * Seluruh keadaan di bawah sudah dideteksi `/admin/sistem` — tapi halaman itu
 * hanya menunjukkannya kepada orang yang kebetulan membukanya. Justru sifat
 * yang membuat kegagalan ini mahal adalah bahwa tidak ada yang membuka halaman
 * apa pun saat itu terjadi: engine mati dan sesi tetap tampak hijau, antrean
 * berhenti dan pesan cuma diam "Mengantre", saldo menyimpang dan tidak ada
 * satu pun galat.
 *
 * **Penanda hariannya disengaja.** Job ini berjalan tiap jam; tanpa penanda,
 * engine yang mati semalaman menghasilkan dua belas baris yang sama dan lonceng
 * berhenti dibaca siapa pun. Dengan penanda bertanggal, satu keadaan
 * menghasilkan satu kabar per hari — cukup untuk mengingatkan, tidak cukup
 * untuk membuat orang mengabaikannya.
 *
 * Kegagalan job ini sendiri tidak boleh menjatuhkan apa pun: ia pengawas, dan
 * pengawas yang jatuh tidak boleh membawa serta yang diawasinya.
 */
class PantauKesehatanJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(Notifier $notifikasi, WhatsAppNotifier $wa, BalanceService $saldo): void
    {
        $hariIni = now()->format('Y-m-d');

        $this->engine($notifikasi, $hariIni);
        $this->antrean($notifikasi, $hariIni);
        $this->pengirim($notifikasi, $wa, $hariIni);
        $this->emailKeluar($notifikasi, $hariIni);
        $this->saldo($notifikasi, $saldo, $hariIni);
        $this->kapasitas($notifikasi, $hariIni);
        $this->debugProduksi($notifikasi, $hariIni);
    }

    /**
     * Engine mati berarti TIDAK SATU PUN pesan bisa keluar, untuk seluruh
     * pelanggan sekaligus. Ini kegagalan paling mahal di sistem ini, dan
     * dashboard tetap menampilkan sesi hijau sampai penyelaras berikutnya.
     */
    private function engine(Notifier $notifikasi, string $hariIni): void
    {
        try {
            $jawaban = Http::timeout((int) config('gateway.engine.connect_timeout', 5))
                ->withToken(config('gateway.engine.token'))
                ->get(rtrim(config('gateway.engine.url'), '/').'/health');

            if ($jawaban->successful()) {
                return;
            }

            $sebab = 'Menjawab dengan status '.$jawaban->status().'.';
        } catch (\Throwable $e) {
            $sebab = $e->getMessage();
        }

        $notifikasi->keAdmin(
            type: 'sistem.engine_mati',
            title: 'Engine WhatsApp tidak terjangkau',
            body: $sebab.' Selama ini berlangsung, tidak satu pun pesan bisa keluar — '
                .'dan dashboard pelanggan tetap menampilkan sesi mereka hijau.',
            url: route('admin.system'),
            level: 'danger',
            dedupe: "sistem.engine_mati:{$hariIni}",
        );
    }

    /**
     * Yang diukur GERAKANNYA, bukan panjang antreannya — broadcast seribu nomor
     * memang wajar menyisakan pekerjaan berjam-jam.
     */
    private function antrean(Notifier $notifikasi, string $hariIni): void
    {
        $antrean = KesehatanAntrean::periksa();

        if ($antrean['macet']) {
            $notifikasi->keAdmin(
                type: 'sistem.antrean_macet',
                title: 'Antrean pesan berhenti bergerak',
                body: $antrean['menunggu'].' pesan menunggu dan tidak satu pun terkirim sejak '
                    .($antrean['terakhirBergerak']?->diffForHumans() ?? 'entah kapan')
                    .'. Worker yang mati tidak menghasilkan galat apa pun — pesan cuma diam "Mengantre".',
                url: route('admin.system'),
                level: 'danger',
                dedupe: "sistem.antrean_macet:{$hariIni}",
            );
        }

        if (($antrean['gagal'] ?? 0) > 0) {
            $notifikasi->keAdmin(
                type: 'sistem.job_gagal',
                title: $antrean['gagal'].' job gagal menumpuk',
                body: 'Job yang gagal tidak dicoba lagi sendiri; pesan di dalamnya tidak akan pernah terkirim.',
                url: route('admin.system'),
                level: 'warning',
                dedupe: "sistem.job_gagal:{$hariIni}",
            );
        }
    }

    /** Tanpa sesi pengirim, SELURUH pemberitahuan pelanggan diam tanpa gejala. */
    private function pengirim(Notifier $notifikasi, WhatsAppNotifier $wa, string $hariIni): void
    {
        if ($wa->ready()) {
            return;
        }

        $notifikasi->keAdmin(
            type: 'sistem.pengirim_mati',
            title: 'Pemberitahuan WhatsApp tidak berjalan',
            body: 'Tidak ada sesi pengirim yang siap. Pelanggan tidak dikabari saat pembayarannya '
                .'lunas, kuotanya habis, atau nomornya terputus — dan tidak ada satu pun yang gagal '
                .'secara terlihat.',
            url: route('admin.exemptions'),
            level: 'danger',
            dedupe: "sistem.pengirim_mati:{$hariIni}",
        );
    }

    /**
     * `MAIL_MAILER=log` diterima Laravel tanpa keluhan apa pun — dari dalam
     * aplikasi, "terkirim" dan "ditulis ke berkas log" tampak persis sama.
     */
    private function emailKeluar(Notifier $notifikasi, string $hariIni): void
    {
        $mailer = config('mail.default');
        $siap = ! in_array($mailer, ['log', 'array', 'null'], true)
            && ! ($mailer === 'smtp' && blank(config('mail.mailers.smtp.host')))
            && filled(config('mail.from.address'));

        if ($siap) {
            return;
        }

        $notifikasi->keAdmin(
            type: 'sistem.email_mati',
            title: 'Email keluar belum berjalan',
            body: "MAIL_MAILER sekarang '{$mailer}'. Tidak ada satu pun email yang benar-benar "
                .'terkirim — pemulihan kata sandi dan pemberitahuan tiket cuma janji.',
            url: route('admin.system'),
            level: 'warning',
            dedupe: "sistem.email_mati:{$hariIni}",
        );
    }

    /** Saldo yang tidak bisa direkonsiliasi adalah uang pelanggan yang hilang jejaknya. */
    private function saldo(Notifier $notifikasi, BalanceService $saldo, string $hariIni): void
    {
        $tidakCocok = $saldo->workspaceTidakCocok();

        if ($tidakCocok->isEmpty()) {
            return;
        }

        $notifikasi->keAdmin(
            type: 'sistem.saldo_tidak_cocok',
            title: $tidakCocok->count().' workspace saldonya tidak cocok dengan buku besar',
            body: $tidakCocok->take(3)->map(fn ($w) => $w->name.' (selisih Rp '
                .number_format((int) $w->balance - (int) $w->buku_besar, 0, ',', '.').')')->join('; ')
                .'. Selisih saldo tidak menghasilkan galat apa pun; yang menemukannya akan jadi '
                .'pelanggan yang merasa saldonya berkurang sendiri.',
            url: route('admin.system'),
            level: 'danger',
            dedupe: "sistem.saldo_tidak_cocok:{$hariIni}",
        );
    }

    /**
     * Kapasitas adalah pembatas jualan, bukan kode.
     *
     * Begitu penuh, pelanggan berikutnya yang MEMBAYAR tidak bisa menautkan
     * nomornya — dan itu ketahuan dari keluhan, bukan dari mana pun.
     */
    private function kapasitas(Notifier $notifikasi, string $hariIni): void
    {
        $batas = (int) config('gateway.engine.max_sessions');

        if ($batas <= 0) {
            return;
        }

        $hidup = DB::table('wa_sessions')
            ->whereIn('status', ['connected', 'connecting', 'qr'])
            ->whereNull('deleted_at')
            ->count();

        if ($hidup < $batas) {
            return;
        }

        $notifikasi->keAdmin(
            type: 'sistem.kapasitas_penuh',
            title: "Kapasitas sesi penuh ({$hidup}/{$batas})",
            body: 'Pelanggan berikutnya yang membayar tidak akan bisa menautkan nomornya. '
                .'Naikkan WA_MAX_SESSIONS hanya kalau RAM server memang cukup — '
                .'yang dibunuh OOM killer belum tentu Chromium.',
            url: route('admin.system'),
            level: 'warning',
            dedupe: "sistem.kapasitas_penuh:{$hariIni}",
        );
    }

    /** Halaman galat memamerkan seluruh isi environment kepada pengunjung. */
    private function debugProduksi(Notifier $notifikasi, string $hariIni): void
    {
        if (! config('app.debug') || ! app()->environment('production')) {
            return;
        }

        $notifikasi->keAdmin(
            type: 'sistem.debug_menyala',
            title: 'APP_DEBUG menyala di produksi',
            body: 'Setiap halaman galat memamerkan kata sandi database, secret HMAC engine, '
                .'dan kredensial Google kepada siapa pun yang membukanya.',
            url: route('admin.system'),
            level: 'danger',
            dedupe: "sistem.debug_menyala:{$hariIni}",
        );
    }
}
