<?php

namespace App\Jobs;

use App\Models\WaSession;
use App\Services\Notifications\Notifier;
use App\Services\SessionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Menyelaraskan status sesi dengan keadaan sebenarnya di engine, lalu
 * menyambungkan ulang sesi yang putus. Callback dari engine sudah menangani
 * kasus normal; job ini menutup kasus engine mati mendadak sehingga tidak
 * sempat mengabari siapa pun.
 */
class SyncSessionStatusJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(SessionService $sessions): void
    {
        $candidates = WaSession::query()
            ->with('workspace')
            ->whereIn('status', ['connected', 'connecting', 'disconnected'])
            ->where('driver', 'wwebjs')
            ->get();

        foreach ($candidates as $session) {
            try {
                $before = $session->status;
                $session = $sessions->syncStatus($session);

                if ($before === 'connected' && $session->status !== 'connected') {
                    Log::warning('Sesi WhatsApp terputus', ['session_id' => $session->id]);
                }

                /*
                 | Menyelaraskan status boleh untuk sesi mana pun — itu cuma
                 | membaca. Yang TIDAK boleh adalah menjalankannya lagi untuk
                 | workspace yang layanannya sudah mati: `releaseSessions()`
                 | melepasnya setelah masa tenggang, lalu job ini menjalankannya
                 | lagi satu menit kemudian, tiap menit, selamanya. Penegakan
                 | langganan yang bisa dibatalkan oleh penjadwal kita sendiri
                 | bukan penegakan.
                */
                if ($session->status !== 'disconnected'
                    || ! $session->auto_reconnect
                    || ! $session->workspace?->isActive()) {
                    continue;
                }

                /*
                 | Batas percobaan. Tanpa ini, sesi yang penyebab kegagalannya
                 | TIDAK akan membaik sendiri dicoba lagi tiap menit selamanya —
                 | dan tiap percobaan membebani engine dan soket WhatsApp.
                 |
                 | Yang berhenti hanya penyambungan OTOMATIS. Barisnya sengaja
                 | TIDAK ditandai `failed`: status itu mengeluarkannya dari
                 | pemulihan otomatis `BootstrapController`, dan sesi yang
                 | kredensialnya masih utuh tidak boleh kehilangan pemulihan
                 | gara-gara satu rentetan kegagalan yang mungkin sudah lewat.
                 | Tombol Hubungkan tetap bekerja, dan menekannya mengembalikan
                 | jatahnya penuh.
                */
                if (! $session->bolehSambungOtomatis()) {
                    Log::warning('Penyambungan otomatis dihentikan setelah percobaan berulang', [
                        'session_id' => $session->id,
                        'percobaan' => $session->connect_failures,
                    ]);

                    $this->beriTahuMenyerah($session);

                    continue;
                }

                // Dicatat SEBELUM mencoba, bukan sesudah. Kegagalan di jalur ini
                // bisa datang sebagai event yang hilang di tengah jalan, dan
                // penghitung yang cuma naik saat kegagalan terlapor adalah
                // penghitung yang tidak pernah naik justru pada kasus terburuk.
                $session->catatPercobaanSambung();

                $sessions->connect($session);
            } catch (\Throwable $e) {
                Log::warning('Gagal menyelaraskan status sesi', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Nomor yang berhenti dicoba wajib mengatakan kenapa.
     *
     * Berhenti mencoba diam-diam menghasilkan gejala yang persis sama dengan
     * gangguan: nomor terputus, tidak ada yang terjadi, tidak ada penjelasan.
     * Pelanggan menyimpulkan satu-satunya hal yang masuk akal — ada yang rusak —
     * lalu menghubungi kami untuk sesuatu yang tinggal ditekan sekali.
     *
     * `last_error` dipakai karena kolom itu memang muka pengguna: ia tampil di
     * kartu sesi dan di modal QR. Penandanya memuat jumlah percobaan supaya satu
     * rentetan menghasilkan satu kabar, bukan satu kabar tiap menit.
     */
    private function beriTahuMenyerah(WaSession $session): void
    {
        /*
         | Dua kalimat, karena keduanya menuntut tindakan yang berbeda.
         |
         | Nomor yang PERNAH tertaut punya kredensial tersimpan; menekan
         | Hubungkan biasanya cukup. Nomor yang BELUM PERNAH tertaut tidak punya
         | apa-apa untuk dipulihkan — yang dibutuhkan orang di situ adalah scan
         | QR, dan menyuruhnya menekan Hubungkan berulang kali adalah menyuruh
         | mengulang hal yang sudah terbukti tidak berhasil.
         |
         | Produksi 8 September 2026: satu nomor punya empat baris sesi yang
         | tidak satu pun pernah `connected`. Empat kali orang mencoba, empat
         | kali tidak ada yang memberi tahu apa yang sebenarnya kurang.
        */
        $kalimat = $session->connected_at === null
            ? 'Nomor ini belum pernah berhasil ditautkan setelah '
                .$session->connect_failures.' percobaan, jadi kami berhenti mencoba. '
                .'Buka Hubungkan lalu scan kode QR-nya dari WhatsApp di ponsel '
                .'(Perangkat tertaut). Kalau kode QR-nya tidak pernah muncul, hubungi kami.'
            : 'Kami berhenti mencoba menyambungkan nomor ini setelah '
                .$session->connect_failures.' percobaan. Tekan Hubungkan untuk mencoba lagi.';

        if ($session->last_error !== $kalimat) {
            $session->forceFill(['last_error' => $kalimat])->save();
        }

        if (! $session->workspace) {
            return;
        }

        app(Notifier::class)->keWorkspace(
            workspace: $session->workspace,
            type: 'session.reconnect_gave_up',
            title: "Nomor {$session->name} berhenti disambungkan otomatis",
            body: $kalimat,
            url: route('sessions.index', absolute: false),
            level: 'danger',
            dedupe: 'reconnect-gave-up:'.$session->id.':'.$session->connect_failures,
        );
    }
}
