<?php

namespace App\Services\Providers;

use App\Models\Message;
use App\Models\WaSession;

/**
 * Kontrak satu provider WhatsApp.
 *
 * Ada tiga jenis provider yang mungkin dipakai Flustra:
 *  - wwebjs    : otomasi WhatsApp Web (gratis, scan QR, tidak resmi)
 *  - cloud_api : Meta Cloud API / BSP seperti Twilio (resmi, berbayar, template)
 *  - fonnte    : gateway pihak ketiga berbayar
 *
 * Interface ini sengaja ada sejak awal supaya pelanggan bisa dipindah dari
 * wwebjs ke API resmi tanpa mengubah REST API publik atau integrasi mereka.
 */
interface WhatsAppProvider
{
    /**
     * Menyalakan sesi. Untuk wwebjs ini membuka browser dan memicu QR;
     * untuk provider berbasis API ini biasanya no-op.
     */
    public function startSession(WaSession $session): void;

    public function stopSession(WaSession $session): void;

    /**
     * Memutus tautan perangkat di sisi WhatsApp dan membuang kredensial sesi.
     * Berbeda dengan stopSession: setelah logout wajib scan QR ulang.
     */
    public function logoutSession(WaSession $session): void;

    /**
     * @return array{status: string, phone_number?: ?string, push_name?: ?string}
     */
    public function status(WaSession $session): array;

    /**
     * Mengirim pesan yang sudah tercatat di tabel messages.
     *
     * @return array{wa_message_id: ?string, raw: array<string, mixed>}
     */
    public function send(WaSession $session, Message $message): array;
}
