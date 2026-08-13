<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| REST API publik. Dipakai flustra-erp, flustra-web, flustra-pricing,
| flustra-helpdesk, dan (nanti) pelanggan SaaS.
|
| Autentikasi memakai API key per tenant lewat header X-Api-Key.
| Rate limit dibatasi per kunci, bukan per IP, supaya satu pelanggan tidak
| bisa menghabiskan jatah pelanggan lain yang kebetulan sekantor.
*/

Route::prefix('v1')->middleware('apikey')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::middleware('throttle:api-key')->group(function (): void {
        // Nama rute diberi awalan `api.` supaya tidak bertabrakan dengan rute
        // dashboard yang memakai nama `sessions.index` dan seterusnya. Tanpa
        // awalan ini, route('sessions.index') di controller dan layout akan
        // menghasilkan URL API, bukan halaman dashboard.
        Route::apiResource('sessions', SessionController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['sessions' => 'id'])
            ->names([
                'index' => 'api.sessions.index',
                'store' => 'api.sessions.store',
                'show' => 'api.sessions.show',
                'destroy' => 'api.sessions.destroy',
            ]);

        Route::post('sessions/{id}/connect', [SessionController::class, 'connect']);
        Route::post('sessions/{id}/disconnect', [SessionController::class, 'disconnect']);
        Route::post('sessions/{id}/logout', [SessionController::class, 'logout']);
        Route::get('sessions/{id}/qr', [SessionController::class, 'qr']);

        Route::post('messages/text', [MessageController::class, 'text']);
        Route::post('messages/media', [MessageController::class, 'media']);
        Route::post('messages/bulk', [MessageController::class, 'bulk']);
        Route::post('messages/template', [MessageController::class, 'template']);
        Route::get('messages', [MessageController::class, 'index']);
        Route::get('messages/{id}', [MessageController::class, 'show']);

        Route::get('webhooks', [WebhookController::class, 'index']);
        Route::post('webhooks', [WebhookController::class, 'store']);
        Route::delete('webhooks/{id}', [WebhookController::class, 'destroy']);

        // Scope terpisah: kemampuan mengirim OTP jauh lebih sensitif daripada
        // mengirim pesan biasa, jadi tidak diberikan ke kunci integrasi umum.
        Route::middleware('apikey:otp')->group(function (): void {
            Route::post('otp/send', [OtpController::class, 'send']);
            Route::post('otp/verify', [OtpController::class, 'verify']);
        });
    });
});
