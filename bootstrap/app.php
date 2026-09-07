<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\VerifyEngineSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Endpoint yang hanya dipanggil oleh engine Node, bukan publik.
            // Dipisah supaya tidak ikut rate limit & middleware API publik.
            Route::middleware('internal')
                ->prefix('internal')
                ->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Berlaku untuk SEMUA respons — halaman, API, dan callback engine
        // sekaligus. Dipasang global dan bukan per-grup karena header yang
        // hilang di satu rute saja sudah cukup: satu halaman yang bisa
        // di-iframe adalah satu halaman yang bisa dipakai clickjacking.
        $middleware->append(HeaderKeamanan::class);

        $middleware->alias([
            'apikey' => AuthenticateApiKey::class,
            'workspace' => EnsureWorkspaceSelected::class,
            'subscription' => EnsureSubscriptionActive::class,
            'admin' => EnsureSuperAdmin::class,
            '2fa' => EnsureTwoFactor::class,
        ]);

        /*
         | Faktor kedua ditegakkan pada SELURUH halaman web terautentikasi, bukan
         | dipasang per rute.
         |
         | Dipasang di grup `web` supaya rute baru ikut terjaga tanpa ada yang
         | perlu mengingat menambahkannya. Penjagaan yang harus diingat adalah
         | penjagaan yang suatu saat terlewat, dan yang terlewat di sini berarti
         | satu halaman yang bisa dibuka hanya dengan kata sandi yang bocor.
         |
         | API sengaja TIDAK ikut: ia diautentikasi dengan API key, bukan sesi
         | peramban, dan tidak ada manusia di ujungnya yang bisa mengetik kode.
        */
        $middleware->web(append: [EnsureTwoFactor::class]);

        $middleware->group('internal', [
            VerifyEngineSignature::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Request ke /api/* selalu dijawab JSON, tidak pernah halaman HTML error.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->is('internal/*') || $request->expectsJson()
        );
    })->create();
