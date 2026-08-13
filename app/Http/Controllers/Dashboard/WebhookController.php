<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantSelected;
use App\Jobs\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = EnsureTenantSelected::from($request);

        return view('dashboard.webhooks.index', [
            'webhooks' => $tenant->webhooks()->latest()->get(),
            'deliveries' => \App\Models\WebhookDelivery::whereIn('webhook_id', $tenant->webhooks()->pluck('id'))
                ->latest()
                ->limit(20)
                ->get(),
            'availableEvents' => [
                WebhookDispatcher::EVENT_MESSAGE_RECEIVED => 'Pesan masuk',
                WebhookDispatcher::EVENT_MESSAGE_STATUS => 'Status pengiriman berubah',
                WebhookDispatcher::EVENT_SESSION_STATUS => 'Status sesi berubah',
                WebhookDispatcher::EVENT_SESSION_QR => 'QR baru tersedia',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in([
                WebhookDispatcher::EVENT_MESSAGE_RECEIVED,
                WebhookDispatcher::EVENT_MESSAGE_STATUS,
                WebhookDispatcher::EVENT_SESSION_STATUS,
                WebhookDispatcher::EVENT_SESSION_QR,
            ])],
        ]);

        $webhook = EnsureTenantSelected::from($request)->webhooks()->create([
            'url' => $data['url'],
            'events' => $data['events'] ?? null,
            'secret' => Str::random(48),
        ]);

        AuditLog::record('webhook.created', $webhook, ['url' => $webhook->url]);

        return back()->with('status', 'Webhook ditambahkan. Simpan signing secret-nya untuk memverifikasi payload.');
    }

    /**
     * Mengirim payload contoh supaya tenant bisa memastikan endpoint mereka
     * menerima dan memverifikasi tanda tangan dengan benar sebelum ada trafik
     * sungguhan.
     */
    public function test(Request $request, int $id): RedirectResponse
    {
        $webhook = EnsureTenantSelected::from($request)->webhooks()->findOrFail($id);

        DeliverWebhookJob::dispatch($webhook->id, 'webhook.test', [
            'message' => 'Ini kiriman uji coba dari Flustra WA Gateway.',
            'sent_at' => now()->toIso8601String(),
        ]);

        return back()->with('status', 'Kiriman uji coba diantre. Cek daftar pengiriman beberapa detik lagi.');
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $webhook = EnsureTenantSelected::from($request)->webhooks()->findOrFail($id);

        $webhook->update([
            'is_active' => ! $webhook->is_active,
            // Menyalakan ulang setelah dinonaktifkan otomatis harus mengosongkan
            // hitungan gagal, kalau tidak ia langsung mati lagi.
            'consecutive_failures' => 0,
        ]);

        return back()->with('status', $webhook->is_active ? 'Webhook diaktifkan.' : 'Webhook dinonaktifkan.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $webhook = EnsureTenantSelected::from($request)->webhooks()->findOrFail($id);

        AuditLog::record('webhook.deleted', $webhook, ['url' => $webhook->url]);
        $webhook->delete();

        return back()->with('status', 'Webhook dihapus.');
    }
}
