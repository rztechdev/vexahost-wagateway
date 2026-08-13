<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureTenantSelected;
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
        $middleware->alias([
            'apikey' => AuthenticateApiKey::class,
            'tenant' => EnsureTenantSelected::class,
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
