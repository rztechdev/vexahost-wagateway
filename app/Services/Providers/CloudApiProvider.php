<?php

namespace App\Services\Providers;

use App\Models\Message;
use App\Models\WaSession;

/**
 * Slot untuk WhatsApp Business Platform resmi (Meta Cloud API, atau lewat BSP
 * seperti Twilio). Belum diimplementasi — sengaja ada supaya bentuk data,
 * REST API publik, dan dashboard tidak perlu berubah saat driver ini diaktifkan.
 *
 * Yang dibutuhkan sebelum driver ini bisa dipakai:
 *  - Meta Business Manager terverifikasi + nomor khusus (tidak bisa nomor
 *    yang sudah dipakai aplikasi WhatsApp biasa)
 *  - Template pesan yang disetujui Meta untuk semua pesan di luar jendela
 *    layanan 24 jam; hanya balasan dalam jendela itu yang boleh bebas format
 *  - Kolom baru di wa_sessions untuk phone_number_id, waba_id, dan token
 *  - Tabel template terpisah yang menyimpan status persetujuan Meta, karena
 *    message_templates saat ini bebas-format tanpa proses persetujuan
 *
 * Biayanya per pesan terkirim (tarif Meta per kategori & negara, ditambah
 * biaya per pesan BSP), berbeda dari wwebjs yang gratis tapi tidak resmi.
 * Lihat docs/PERBANDINGAN_PROVIDER.md.
 */
class CloudApiProvider implements WhatsAppProvider
{
    public function startSession(WaSession $session): void
    {
        $this->notImplemented();
    }

    public function stopSession(WaSession $session): void
    {
        $this->notImplemented();
    }

    public function logoutSession(WaSession $session): void
    {
        $this->notImplemented();
    }

    public function status(WaSession $session): array
    {
        return ['status' => 'failed', 'error' => 'Driver cloud_api belum tersedia.'];
    }

    public function send(WaSession $session, Message $message): array
    {
        $this->notImplemented();
    }

    private function notImplemented(): never
    {
        throw ProviderException::permanent(
            'Driver cloud_api (WhatsApp Business API resmi) belum diimplementasi. '
            .'Gunakan driver wwebjs, atau lihat docs/PERBANDINGAN_PROVIDER.md.'
        );
    }
}
