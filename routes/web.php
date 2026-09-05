<?php

use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Dashboard\ApiKeyController;
use App\Http\Controllers\Dashboard\BillingController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\MessageController;
use App\Http\Controllers\Dashboard\SessionController;
use App\Http\Controllers\Dashboard\TemplateController;
use App\Http\Controllers\Dashboard\WebhookController;
use App\Http\Controllers\Dashboard\WorkspaceController;
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
| Autentikasi lokal: gateway memegang form login dan register-nya sendiri,
| tidak menumpang pintu masuk aplikasi lain.
*/
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);

    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function (): void {
    // Pembuatan workspace ada di luar middleware `workspace`, karena middleware
    // itulah yang mengarahkan ke sini saat pengguna belum punya workspace.
    Route::get('onboarding', [WorkspaceController::class, 'createForm'])->name('onboarding.create');
    Route::post('onboarding', [WorkspaceController::class, 'create'])->name('onboarding.store');
    Route::post('workspaces/{id}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    /*
    | `subscription` menempel di dalam `workspace`, bukan menggantikannya: ia
    | butuh workspace yang sudah terpilih untuk tahu langganan siapa yang
    | diperiksa. Aturannya satu — GET selalu lewat, selain GET ditolak saat
    | langganan tidak berlaku — jadi rute baru otomatis ikut terjaga tanpa
    | perlu didaftarkan di mana pun.
    */
    Route::middleware(['workspace', 'subscription'])->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        /*
        | Empat alamat terpisah, bukan satu halaman panjang. Yang terpenting
        | dari pemisahan ini bukan kerapian, tapi bahwa tiap langkah bisa
        | ditautkan langsung — spanduk "langganan habis" menuju pilih paket,
        | pengingat WhatsApp menuju halaman bayar — tanpa berharap pengguna
        | menggulir ke bagian yang benar.
        */
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/paket', [BillingController::class, 'plans'])->name('billing.plans');
        Route::get('billing/riwayat', [BillingController::class, 'history'])->name('billing.history');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::get('billing/invoices/{id}', [BillingController::class, 'invoice'])->name('billing.invoice');
        Route::post('billing/invoices/{id}/penagihan', [BillingController::class, 'saveBillingDetails'])->name('billing.details');
        Route::post('billing/invoices/{id}/bukti', [BillingController::class, 'uploadProof'])->name('billing.proof.upload');
        Route::get('billing/invoices/{id}/bukti', [BillingController::class, 'proof'])->name('billing.proof');
        Route::post('billing/invoices/{id}/batal', [BillingController::class, 'cancelInvoice'])->name('billing.invoice.cancel');

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

        Route::get('settings', [WorkspaceController::class, 'settings'])->name('settings');
        Route::put('settings', [WorkspaceController::class, 'update'])->name('settings.update');
        Route::delete('settings', [WorkspaceController::class, 'destroy'])->name('settings.destroy');
        Route::post('settings/members', [WorkspaceController::class, 'addMember'])->name('settings.members.add');
        Route::delete('settings/members/{userId}', [WorkspaceController::class, 'removeMember'])->name('settings.members.remove');
    });
});

/*
| Panel admin. Di luar middleware `workspace` dengan sengaja: halaman-halaman
| ini justru bertugas melihat seluruh workspace sekaligus, dan berangkat dari
| satu workspace yang sedang dipilih hanya akan menghalangi.
|
| `EnsureSuperAdmin` menjawab 404, bukan 403 — bagi siapa pun yang bukan super
| admin, panel ini sebaiknya tidak tampak pernah ada.
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminOverviewController::class)->name('overview');

    Route::get('workspaces', [AdminWorkspaceController::class, 'index'])->name('workspaces');
    Route::post('workspaces/{id}/paket', [AdminWorkspaceController::class, 'changePlan'])->name('workspaces.plan');
    Route::post('workspaces/{id}/perpanjang', [AdminWorkspaceController::class, 'extend'])->name('workspaces.extend');
    Route::post('workspaces/{id}/tangguhkan', [AdminWorkspaceController::class, 'toggleSuspend'])->name('workspaces.suspend');
    Route::post('workspaces/{id}/batas', [AdminWorkspaceController::class, 'overrideLimits'])->name('workspaces.limits');

    Route::get('tagihan', [AdminInvoiceController::class, 'index'])->name('invoices');
    Route::post('tagihan/{id}/lunas', [AdminInvoiceController::class, 'markPaid'])->name('invoices.paid');
    Route::post('tagihan/{id}/status', [AdminInvoiceController::class, 'updateStatus'])->name('invoices.status');
    Route::get('tagihan/{id}/bukti', [AdminInvoiceController::class, 'proof'])->name('invoices.proof');

    Route::get('sesi', [AdminSessionController::class, 'index'])->name('sessions');
    Route::post('sesi/{id}/putus', [AdminSessionController::class, 'disconnect'])->name('sessions.disconnect');

    Route::get('pengguna', [AdminUserController::class, 'index'])->name('users');
    Route::post('pengguna/{id}/super-admin', [AdminUserController::class, 'toggleSuperAdmin'])->name('users.super');
});
