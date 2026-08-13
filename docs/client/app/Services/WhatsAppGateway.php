<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien Flustra WA Gateway.
 *
 * Disalin apa adanya ke setiap aplikasi Flustra yang perlu mengirim WhatsApp.
 * Sumber aslinya ada di repo flustra-wa (docs/client/) — ubah di sana dulu,
 * lalu salin ulang, mengikuti pola yang sama dengan CentralAuthClient dan
 * SloNotifier.
 *
 * Prinsipnya: pengiriman WhatsApp tidak boleh pernah menggagalkan proses yang
 * memanggilnya. Semua kegagalan dicatat ke log dan dikembalikan sebagai false,
 * tidak pernah dilempar sebagai exception. Invoice tetap harus tersimpan meski
 * notifikasi WhatsApp-nya gagal terkirim.
 */
class WhatsAppGateway
{
    /**
     * Mengirim satu pesan teks.
     *
     * @return bool true kalau gateway menerima pesan untuk diantre. Status
     *              pengiriman sebenarnya menyusul lewat webhook, bukan di sini.
     */
    public static function send(?string $phone, string $message, ?string $sessionId = null): bool
    {
        if (! config('whatsapp.enabled') || ! config('whatsapp.key')) {
            return false;
        }

        $phone = self::normalize($phone);

        if ($phone === null || trim($message) === '') {
            return false;
        }

        try {
            $response = self::request()->post('/api/v1/messages/text', array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to' => $phone,
                'message' => $message,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Gateway WhatsApp tidak bisa dihubungi', [
                'phone' => self::mask($phone),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        Log::warning('Gateway WhatsApp menolak pesan', [
            'phone' => self::mask($phone),
            'status' => $response->status(),
            'error' => $response->json('error.message') ?? $response->body(),
        ]);

        return false;
    }

    /**
     * Mengirim satu pesan ke banyak nomor sekaligus. Gateway yang mengatur
     * jeda antar pesannya, jadi pemanggil tidak perlu (dan tidak boleh)
     * membuat perulangan sendiri untuk broadcast besar.
     *
     * @param  array<int, string>  $phones
     */
    public static function broadcast(array $phones, string $message, ?string $sessionId = null): bool
    {
        if (! config('whatsapp.enabled') || ! config('whatsapp.key')) {
            return false;
        }

        $targets = array_values(array_filter(array_map(self::normalize(...), $phones)));

        if ($targets === []) {
            return false;
        }

        try {
            $response = self::request()->post('/api/v1/messages/bulk', array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to' => $targets,
                'message' => $message,
            ]));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Broadcast WhatsApp gagal', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Mengirim memakai template yang tersimpan di gateway.
     *
     * @param  array<string, string>  $variables
     */
    public static function template(?string $phone, string $template, array $variables = [], ?string $sessionId = null): bool
    {
        if (! config('whatsapp.enabled') || ! config('whatsapp.key')) {
            return false;
        }

        $phone = self::normalize($phone);

        if ($phone === null) {
            return false;
        }

        try {
            $response = self::request()->post('/api/v1/messages/template', array_filter([
                'session_id' => $sessionId ?? config('whatsapp.session'),
                'to' => $phone,
                'template' => $template,
                'variables' => $variables,
            ]));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Pengiriman template WhatsApp gagal', [
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Normalisasi ke format 62xxxxxxxxxx. Aturannya sengaja sama persis dengan
     * App\Support\WhatsAppLink di flustra-web supaya nomor yang sudah tersimpan
     * di berbagai aplikasi tidak berubah arti.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^62[1-9][0-9]{7,12}$/', $digits) === 1 ? $digits : null;
    }

    private static function mask(string $phone): string
    {
        return substr($phone, 0, 4).'****'.substr($phone, -4);
    }

    private static function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('whatsapp.url'), '/'))
            ->withHeader('X-Api-Key', config('whatsapp.key'))
            ->connectTimeout(config('whatsapp.connect_timeout'))
            ->timeout(config('whatsapp.timeout'))
            ->acceptJson();
    }
}
