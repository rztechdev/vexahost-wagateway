<?php

namespace App\Services\Providers;

use App\Models\Message;
use App\Models\WaSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Driver whatsapp-web.js. Semua kerja beratnya ada di engine Node terpisah;
 * kelas ini hanya klien HTTP tipis ke engine tersebut.
 */
class WwebjsProvider implements WhatsAppProvider
{
    public function startSession(WaSession $session): void
    {
        $hasBackup = false;
        $backupData = null;

        if ($session->backups()->exists()) {
            $backup = $session->latestBackup();
            if ($backup && Storage::disk($backup->disk)->exists($backup->path)) {
                $path = (string) $backup->path;
                if (! str_ends_with($path, 'session.json')) {
                    Log::warning('Backup sesi diabaikan: format lama (.zip/non-JSON) tidak kompatibel dengan Baileys', [
                        'session_id' => $session->id,
                        'path' => $path,
                    ]);
                } else {
                    $raw = Storage::disk($backup->disk)->get($path);
                    if (! mb_check_encoding($raw, 'UTF-8')) {
                        Log::warning('Backup sesi diabaikan: berkas bukan UTF-8 yang sah', [
                            'session_id' => $session->id,
                            'path' => $path,
                        ]);
                    } elseif (! json_validate($raw)) {
                        Log::warning('Backup sesi diabaikan: isi berkas bukan JSON yang valid', [
                            'session_id' => $session->id,
                            'path' => $path,
                        ]);
                    } else {
                        $backupData = $raw;
                        $hasBackup = true;
                    }
                }
            }
        }

        $payload = [
            'has_backup' => $hasBackup,
        ];

        if ($backupData !== null) {
            $payload['backup_data'] = $backupData;
        }

        $this->request()->post("/sessions/{$session->id}/start", $payload)->throw();
    }

    public function stopSession(WaSession $session): void
    {
        $this->request()->post("/sessions/{$session->id}/stop")->throw();
    }

    public function logoutSession(WaSession $session): void
    {
        $this->request()->post("/sessions/{$session->id}/logout")->throw();
    }

    public function status(WaSession $session): array
    {
        try {
            $response = $this->request()->get("/sessions/{$session->id}/status");
        } catch (ConnectionException $e) {
            // Engine mati bukan berarti sesi hilang — kredensialnya masih ada
            // di volume. Laporkan sebagai disconnected, jangan failed.
            return ['status' => 'disconnected', 'error' => $e->getMessage()];
        }

        if ($response->status() === 404) {
            return ['status' => 'disconnected'];
        }

        $data = $response->throw()->json();

        return [
            'status' => $data['status'] ?? 'disconnected',
            'phone_number' => $data['phone_number'] ?? null,
            'push_name' => $data['push_name'] ?? null,
            // Persentase penarikan riwayat chat setelah QR ter-scan. Hanya ada
            // di memori engine selama proses itu berlangsung.
            'loading_percent' => $data['loading_percent'] ?? null,
            'qr' => $data['qr'] ?? null,
        ];
    }

    public function send(WaSession $session, Message $message): array
    {
        if (! $session->isConnected()) {
            throw new ProviderException("Sesi '{$session->name}' sedang tidak terhubung.");
        }

        $payload = [
            'to' => $message->to_number,
            'type' => $message->type,
            'body' => $message->body,
            'message_id' => $message->id,
        ];

        if ($message->media_path) {
            // Media dikirim sebagai base64 supaya engine tidak perlu akses ke
            // storage Laravel — keduanya container terpisah tanpa volume bersama.
            $payload['media'] = [
                'data' => base64_encode(Storage::disk('media')->get($message->media_path)),
                'mimetype' => $message->media_mime,
                'filename' => $message->media_filename,
            ];
        }

        // Batas waktu panjang khusus di sini: pesan bisa menunggu giliran di
        // antrean anti-ban engine. Perintah sesi lain memakai batas pendek —
        // di sana ada manusia yang menunggu tombolnya selesai.
        $response = $this->request(config('gateway.engine.send_timeout'))
            ->post("/sessions/{$session->id}/messages", $payload);

        if ($response->failed()) {
            throw $this->toException($response);
        }

        $data = $response->json();

        return [
            'wa_message_id' => $data['wa_message_id'] ?? null,
            'raw' => $data,
        ];
    }

    private function toException(Response $response): ProviderException
    {
        $body = $response->json() ?? [];
        $message = $body['error'] ?? $response->body();

        // 422 dipakai engine untuk kegagalan yang tidak akan membaik: nomor
        // tidak terdaftar di WhatsApp, tipe media tidak didukung.
        if ($response->status() === 422) {
            return ProviderException::permanent($message, $body);
        }

        return new ProviderException($message, retryable: true, context: $body);
    }

    private function request(?int $timeout = null): PendingRequest
    {
        $config = config('gateway.engine');

        return Http::baseUrl(rtrim($config['url'], '/'))
            ->withHeader('X-Engine-Token', $config['token'])
            ->connectTimeout($config['connect_timeout'])
            ->timeout($timeout ?? $config['timeout'])
            ->acceptJson();
    }
}
