<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\ApiKeyController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\MessageController;
use App\Http\Controllers\Dashboard\SessionController;
use App\Http\Controllers\Dashboard\TemplateController;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\Dashboard\WebhookController;
use App\Http\Controllers\DocsController;
use App\Support\DocsRepository;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

/*
| Dokumentasi. Terbuka untuk publik: isinya penjelasan cara kerja dan cara
| memakai, bukan data pelanggan. Slug dibatasi daftar putih di DocsRepository,
| jadi nilainya tidak pernah dipakai menyusun path berkas.
*/
Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('docs/{slug}', [DocsController::class, 'show'])
    ->whereIn('slug', array_keys(DocsRepository::flat()))
    ->name('docs.show');

/*
| Autentikasi lokal. Setiap aplikasi Flustra memegang form login dan
| register-nya sendiri; flustra-auth berperan menangkap sesi lintas aplikasi,
| bukan menjadi satu-satunya pintu masuk.
*/
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function (): void {
    // Pembuatan workspace ada di luar middleware `tenant`, karena middleware
    // itulah yang mengarahkan ke sini saat pengguna belum punya workspace.
    Route::get('onboarding', [TenantController::class, 'createForm'])->name('onboarding.create');
    Route::post('onboarding', [TenantController::class, 'create'])->name('onboarding.store');
    Route::post('tenants/{id}/switch', [TenantController::class, 'switch'])->name('tenants.switch');

    Route::middleware('tenant')->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');
        Route::post('sessions/{id}/connect', [SessionController::class, 'connect'])->name('sessions.connect');
        Route::post('sessions/{id}/disconnect', [SessionController::class, 'disconnect'])->name('sessions.disconnect');
        Route::post('sessions/{id}/logout', [SessionController::class, 'logout'])->name('sessions.logout');
        Route::delete('sessions/{id}', [SessionController::class, 'destroy'])->name('sessions.destroy');
        Route::get('sessions/{id}/status', [SessionController::class, 'status'])->name('sessions.status');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/compose', [MessageController::class, 'compose'])->name('messages.compose');
        Route::post('messages/compose', [MessageController::class, 'send'])->name('messages.send');
        Route::get('messages/{id}', [MessageController::class, 'show'])->name('messages.show');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::put('templates/{id}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{id}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('api-keys/{id}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

        Route::get('webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::post('webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::post('webhooks/{id}/test', [WebhookController::class, 'test'])->name('webhooks.test');
        Route::post('webhooks/{id}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
        Route::delete('webhooks/{id}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

        Route::get('settings', [TenantController::class, 'settings'])->name('settings');
        Route::post('settings/members', [TenantController::class, 'addMember'])->name('settings.members.add');
        Route::delete('settings/members/{userId}', [TenantController::class, 'removeMember'])->name('settings.members.remove');
    });
});
