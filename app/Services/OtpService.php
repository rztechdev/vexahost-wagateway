<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * OTP lewat WhatsApp. Ditaruh di gateway karena hanya di sinilah kemampuan
 * mengirim WhatsApp berada — flustra-auth memanggil endpoint ini, lalu menyimpan
 * hasil verifikasinya di kolom users.phone_verified_at miliknya sendiri.
 *
 * Selalu dikirim dari sesi platform: OTP adalah pesan atas nama Flustra, bukan
 * atas nama workspace.
 */
class OtpService
{
    public function __construct(private readonly MessageDispatcher $dispatcher) {}

    public function send(string $phone, string $purpose, ?string $ip = null, ?Workspace $workspace = null): OtpCode
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw new RuntimeException("Nomor tidak valid: {$phone}");
        }

        $this->guardRateLimit($normalized, $purpose);

        $code = str_pad((string) random_int(0, 999999), config('gateway.otp.length'), '0', STR_PAD_LEFT);

        // Kode lama untuk tujuan yang sama dimatikan supaya hanya kode terbaru
        // yang berlaku — mencegah kode dari SMS lama masih bisa dipakai.
        OtpCode::where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['expires_at' => now()]);

        $otp = OtpCode::create([
            'phone' => $normalized,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds(config('gateway.otp.ttl_seconds')),
            'requested_by_ip' => $ip,
            'workspace_id' => $workspace?->id,
        ]);

        $minutes = (int) ceil(config('gateway.otp.ttl_seconds') / 60);

        $this->dispatcher->queue($this->senderSession(), $normalized, [
            'type' => 'text',
            'body' => "*{$code}* adalah kode verifikasi Flustra Anda.\n\n"
                ."Kode berlaku {$minutes} menit. Jangan bagikan kode ini kepada siapa pun, "
                .'termasuk yang mengaku dari Flustra.',
        ]);

        return $otp;
    }

    public function verify(string $phone, string $purpose, string $code): bool
    {
        $normalized = PhoneNumber::normalize($phone);

        $otp = OtpCode::where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            return false;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }

    private function guardRateLimit(string $phone, string $purpose): void
    {
        $recent = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(config('gateway.otp.resend_cooldown_seconds')))
            ->exists();

        if ($recent) {
            $seconds = config('gateway.otp.resend_cooldown_seconds');

            throw new RuntimeException("Kode baru bisa diminta lagi setelah {$seconds} detik.");
        }

        $today = OtpCode::where('phone', $phone)
            ->where('created_at', '>', now()->subDay())
            ->count();

        // Batas harian menahan penyalahgunaan endpoint OTP untuk membombardir
        // nomor orang lain — pola yang cepat membuat nomor pengirim diblokir.
        if ($today >= config('gateway.otp.max_per_phone_per_day')) {
            throw new RuntimeException('Batas permintaan kode untuk nomor ini sudah tercapai hari ini.');
        }
    }

    private function senderSession(): WaSession
    {
        $id = config('gateway.otp_session_id');

        $session = $id ? WaSession::find($id) : null;

        if (! $session) {
            throw new RuntimeException(
                'Sesi pengirim OTP belum dikonfigurasi. Hubungkan satu sesi di '
                .'dashboard, salin ID sesinya, lalu isi OTP_SESSION_ID di .env.'
            );
        }

        return $session;
    }
}
