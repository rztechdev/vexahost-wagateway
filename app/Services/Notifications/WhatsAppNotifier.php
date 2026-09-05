<?php

namespace App\Services\Notifications;

use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\MessageDispatcher;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pemberitahuan WhatsApp, dikirim lewat gateway kami sendiri.
 *
 * Produk ini menjual pengiriman notifikasi WhatsApp; tidak memakainya untuk
 * memberi tahu pelanggannya sendiri adalah tukang kayu yang rumahnya bocor.
 * Lebih dari itu, di sini WhatsApp adalah **satu-satunya** jalur yang benar
 * sampai: `users` tidak menyimpan nomor telepon, email belum dikonfigurasi, dan
 * spanduk di dashboard hanya terlihat oleh orang yang kebetulan sedang membuka
 * dashboard — justru yang paling jarang dilakukan pelanggan yang gateway-nya
 * berjalan lancar.
 *
 * Tiga aturan yang berlaku di seluruh kelas ini:
 *
 * 1. **Tidak pernah melempar galat.** Notifikasi itu pelengkap; tagihan tetap
 *    harus lunas meski pesannya gagal terkirim. Semua kegagalan dicatat, tidak
 *    ada yang merambat ke pemanggil.
 *
 * 2. **Dikirim dari nomor Flustra, bukan nomor pelanggan.** Memakai sesi
 *    pelanggan berarti kuota mereka yang terpotong dan laporan spam-nya jatuh
 *    ke nomor mereka.
 *
 * 3. **Satu peristiwa, satu pesan.** Penanda di cache mencegah job harian yang
 *    berjalan dua kali mengirim pemberitahuan yang sama dua kali — dan pesan
 *    kembar soal uang membuat orang mengira ia ditagih dua kali.
 */
class WhatsAppNotifier
{
    public function __construct(private readonly MessageDispatcher $dispatcher) {}

    /**
     * Mengirim ke nomor tagihan sebuah workspace.
     *
     * `$sekali` adalah kunci penanda: kalau diisi, pesan dengan kunci yang sama
     * tidak dikirim lagi selama masa yang ditentukan.
     */
    public function toWorkspace(Workspace $workspace, string $pesan, ?string $sekali = null, int $ingatJam = 24): bool
    {
        if ($workspace->is_internal || blank($workspace->billing_phone)) {
            return false;
        }

        return $this->send($workspace->billing_phone, $pesan, $sekali, $ingatJam);
    }

    /**
     * Mengirim ke nomor tim kami sendiri.
     *
     * Dipakai untuk hal yang menuntut tindakan manusia — terutama bukti
     * pembayaran baru, karena selama pencocokan masih manual, tagihan hanya
     * menjadi lunas kalau ada orang yang membukanya di panel.
     */
    public function toAdmin(string $pesan, ?string $sekali = null, int $ingatJam = 24): bool
    {
        $nomor = config('billing.admin_phone');

        if (blank($nomor)) {
            return false;
        }

        return $this->send($nomor, $pesan, $sekali, $ingatJam);
    }

    /**
     * Apakah pemberitahuan bisa dikirim sama sekali.
     *
     * Dipakai panel admin untuk menampilkan keadaannya secara jujur: konfigurasi
     * yang belum lengkap membuat seluruh pemberitahuan diam tanpa satu pun
     * gejala, dan diam adalah gejala yang paling sulit disadari.
     */
    public function ready(): bool
    {
        return $this->senderSession() !== null;
    }

    private function send(string $tujuan, string $pesan, ?string $sekali, int $ingatJam): bool
    {
        $penanda = $sekali ? "wa-notif:{$sekali}" : null;

        if ($penanda && Cache::has($penanda)) {
            return false;
        }

        $pengirim = $this->senderSession();

        if (! $pengirim) {
            Log::info('Pemberitahuan WhatsApp dilewati: tidak ada sesi pengirim yang siap.', [
                'tujuan' => PhoneNumber::mask($tujuan),
            ]);

            return false;
        }

        try {
            $this->dispatcher->queue($pengirim, $tujuan, ['body' => $pesan]);

            if ($penanda) {
                Cache::put($penanda, true, now()->addHours($ingatJam));
            }

            return true;
        } catch (\Throwable $e) {
            // Notifikasi itu pelengkap. Yang memanggilnya sedang mengerjakan
            // sesuatu yang jauh lebih penting — menandai tagihan lunas,
            // menangguhkan langganan — dan itu tidak boleh gagal gara-gara ini.
            Log::warning('Pemberitahuan WhatsApp gagal dikirim.', [
                'tujuan' => PhoneNumber::mask($tujuan),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sesi milik Flustra yang dipakai mengirim.
     *
     * Dikonfigurasi lewat env dan bukan dipilih otomatis dari sesi mana pun
     * yang kebetulan tersambung: memilih sendiri berarti suatu hari
     * pemberitahuan tagihan keluar dari nomor pelanggan lain.
     */
    private function senderSession(): ?WaSession
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
}
