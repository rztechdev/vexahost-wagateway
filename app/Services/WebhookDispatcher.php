<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Workspace;

class WebhookDispatcher
{
    public const EVENT_MESSAGE_RECEIVED = 'message.received';

    public const EVENT_MESSAGE_STATUS = 'message.status';

    public const EVENT_SESSION_STATUS = 'session.status';

    public const EVENT_SESSION_QR = 'session.qr';

    /**
     * Mengantre pengiriman satu event ke semua webhook aktif milik workspace yang
     * berlangganan event tersebut. Selalu lewat queue supaya endpoint workspace
     * yang lambat atau mati tidak menahan request yang sedang berjalan.
     */
    public function dispatch(Workspace $workspace, string $event, array $payload): void
    {
        $webhooks = $workspace->webhooks()->where('is_active', true)->get()
            ->filter(fn ($webhook) => $webhook->listensTo($event));

        foreach ($webhooks as $webhook) {
            DeliverWebhookJob::dispatch($webhook->id, $event, $payload);
        }
    }
}
