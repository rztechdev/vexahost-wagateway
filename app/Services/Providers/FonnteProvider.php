<?php

namespace App\Services\Providers;

use App\Models\Message;
use App\Models\WaSession;
use Illuminate\Support\Facades\Http;

/**
 * Gateway pihak ketiga berbayar. Dipertahankan karena flustra-erp sudah
 * memakainya sebagai fallback (lihat WhatsAppService lama), dan berguna sebagai
 * cadangan saat nomor wwebjs sedang bermasalah.
 *
 * Fonnte mengelola nomornya sendiri, jadi tidak ada QR, status, atau siklus
 * hidup sesi yang perlu dikelola di sini.
 */
class FonnteProvider implements WhatsAppProvider
{
    public function startSession(WaSession $session): void
    {
        // Tidak ada sesi untuk dijalankan — nomor dikelola di sisi Fonnte.
    }

    public function stopSession(WaSession $session): void
    {
        //
    }

    public function logoutSession(WaSession $session): void
    {
        //
    }

    public function status(WaSession $session): array
    {
        return ['status' => $this->token() ? 'connected' : 'failed'];
    }

    public function send(WaSession $session, Message $message): array
    {
        $token = $this->token();

        if (! $token) {
            throw ProviderException::permanent('FONNTE_TOKEN belum diisi.');
        }

        if ($message->media_path) {
            throw ProviderException::permanent('Driver Fonnte pada gateway ini baru mendukung pesan teks.');
        }

        $response = Http::withHeaders(['Authorization' => $token])
            ->timeout(15)
            ->asForm()
            ->post('https://api.fonnte.com/send', [
                'target' => $message->to_number,
                'message' => $message->body,
            ]);

        if ($response->failed()) {
            throw new ProviderException('Fonnte menolak permintaan: '.$response->body());
        }

        $data = $response->json() ?? [];

        // Fonnte membalas HTTP 200 meski gagal; status sebenarnya ada di body.
        if (($data['status'] ?? false) !== true) {
            throw new ProviderException('Fonnte gagal mengirim: '.($data['reason'] ?? 'alasan tidak diketahui'));
        }

        return [
            'wa_message_id' => isset($data['id'][0]) ? (string) $data['id'][0] : null,
            'raw' => $data,
        ];
    }

    private function token(): ?string
    {
        return config('services.fonnte.token');
    }
}
