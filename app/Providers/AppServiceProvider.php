<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Services\Providers\ProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limit REST API dihitung per API key, bukan per IP.
     *
     * Beberapa aplikasi Flustra berjalan di VPS yang sama, sehingga limit
     * per IP akan membuat mereka saling menghabiskan jatah satu sama lain.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api-key', function (Request $request) {
            $key = $request->attributes->get('api_key');

            if (! $key instanceof ApiKey) {
                return Limit::perMinute(30)->by($request->ip());
            }

            $perMinute = $key->rate_limit_per_minute
                ?? $key->tenant->api_rate_limit_per_minute
                ?? config('gateway.defaults.api_rate_limit_per_minute');

            return Limit::perMinute($perMinute)->by("key:{$key->id}");
        });

        // Melindungi form login dari percobaan menebak password.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
