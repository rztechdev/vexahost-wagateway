<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
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
        ]);

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
