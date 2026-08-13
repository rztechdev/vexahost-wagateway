<?php

namespace App\Http\Controllers\Api;

use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OTP WhatsApp untuk seluruh ekosistem Flustra. Dipakai flustra-auth untuk
 * memverifikasi kepemilikan nomor sebelum aplikasi lain boleh mengirim
 * notifikasi ke nomor tersebut.
 */
class OtpController extends ApiController
{
    public function __construct(private readonly OtpService $otp) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $otp = $this->otp->send(
                $data['phone'],
                $data['purpose'] ?? 'phone_verification',
                $request->ip(),
                $this->tenant($request),
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 429);
        }

        return $this->ok([
            'expires_at' => $otp->expires_at->toIso8601String(),
            'resend_after_seconds' => config('gateway.otp.resend_cooldown_seconds'),
        ], 202);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $verified = $this->otp->verify(
            $data['phone'],
            $data['purpose'] ?? 'phone_verification',
            $data['code'],
        );

        if (! $verified) {
            return $this->fail('Kode salah atau sudah kedaluwarsa.', 422);
        }

        return $this->ok(['verified' => true]);
    }
}
