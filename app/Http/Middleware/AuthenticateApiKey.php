<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi REST API publik memakai kunci berformat `fwa_xxxxxxxx.<secret>`.
 *
 * Kunci dikirim lewat header `X-Api-Key`, atau `Authorization: Bearer <kunci>`
 * untuk klien yang lebih nyaman dengan pola bearer.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $plain = $request->header('X-Api-Key') ?: $request->bearerToken();

        if (! $plain || ! str_contains($plain, '.')) {
            return $this->deny('API key tidak disertakan atau formatnya salah.', 401);
        }

        [$prefix, $secret] = explode('.', $plain, 2);

        $key = ApiKey::with('workspace')->where('prefix', $prefix)->first();

        if (! $key || ! Hash::check($secret, $key->key_hash)) {
            return $this->deny('API key tidak dikenali.', 401);
        }

        if (! $key->isUsable()) {
            return $this->deny('API key sudah dicabut atau kedaluwarsa.', 401);
        }

        if (! $key->workspace || ! $key->workspace->isActive()) {
            return $this->deny('Workspace pemilik API key sedang tidak aktif.', 403);
        }

        if ($scope && ! $key->allows($scope)) {
            return $this->deny("API key tidak punya scope '{$scope}'.", 403);
        }

        /*
         | Batas permintaan per menit adalah salah satu pembeda antar paket, dan
         | sampai sekarang kolomnya diisi tanpa pernah ditegakkan di mana pun.
         |
         | Dihitung per WORKSPACE, bukan per API key: menghitung per kunci
         | membuat batasnya bisa dilipatgandakan hanya dengan membuat kunci baru,
         | yang justru gratis. Nilai 0 berarti tanpa batas, mengikuti perjanjian
         | yang sama dengan kuota pesan.
        */
        $batas = (int) $key->workspace->api_rate_limit_per_minute;

        if ($batas > 0) {
            $ember = "api:{$key->workspace_id}";

            if (RateLimiter::tooManyAttempts($ember, $batas)) {
                return $this->deny(
                    "Batas {$batas} permintaan per menit terlampaui. Coba lagi dalam "
                        .RateLimiter::availableIn($ember).' detik.',
                    429
                );
            }

            RateLimiter::hit($ember, 60);
        }

        // Menulis last_used_at pada setiap request akan menjadi satu UPDATE per
        // pesan terkirim. Cukup dicatat sekali per menit — kolom ini hanya untuk
        // informasi "terakhir dipakai" di dashboard.
        $stamp = "apikey:{$key->id}:touched";

        if (! Cache::has($stamp)) {
            $key->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->saveQuietly();

            Cache::put($stamp, true, now()->addMinute());
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('workspace', $key->workspace);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['message' => $message],
        ], $status);
    }
}
