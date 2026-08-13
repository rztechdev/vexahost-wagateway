<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\MessageDispatcher;
use App\Services\Providers\ProviderException;
use App\Services\Providers\ProviderManager;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 120;

    public function __construct(public readonly string $messageId) {}

    /**
     * Jeda antar percobaan naik bertahap. Sesi yang baru putus biasanya butuh
     * puluhan detik untuk tersambung lagi, jadi mengulang secepat mungkin hanya
     * membakar jatah retry tanpa hasil.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ProviderManager $providers, MessageDispatcher $dispatcher, WebhookDispatcher $webhooks): void
    {
        $message = Message::with('session.workspace')->find($this->messageId);

        if (! $message || $message->status !== 'queued') {
            // Sudah terkirim, dibatalkan, atau dipangkas retensi.
            return;
        }

        $session = $message->session;

        if (! $session) {
            $this->fail($message, 'Sesi pengirim sudah dihapus.', $dispatcher, $webhooks);

            return;
        }

        $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1]);

        try {
            $result = $providers->for($session)->send($session, $message);
        } catch (ProviderException $e) {
            if (! $e->retryable || $this->attempts() >= $this->tries) {
                $this->fail($message, $e->getMessage(), $dispatcher, $webhooks);

                return;
            }

            $message->update(['status' => 'queued', 'error' => $e->getMessage()]);

            $this->release($this->backoff()[min($this->attempts() - 1, 2)]);

            return;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan WhatsApp', [
                'message_id' => $message->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->fail($message, $e->getMessage(), $dispatcher, $webhooks);

                return;
            }

            $message->update(['status' => 'queued', 'error' => $e->getMessage()]);

            throw $e;
        }

        $message->update([
            'status' => 'sent',
            'wa_message_id' => $result['wa_message_id'],
            'provider_response' => $result['raw'],
            'sent_at' => now(),
            'error' => null,
        ]);

        $webhooks->dispatch($session->workspace, WebhookDispatcher::EVENT_MESSAGE_STATUS, [
            'message_id' => $message->id,
            'status' => 'sent',
            'to' => $message->to_number,
        ]);
    }

    private function fail(Message $message, string $error, MessageDispatcher $dispatcher, WebhookDispatcher $webhooks): void
    {
        $message->update(['status' => 'failed', 'error' => $error]);

        if ($workspace = $message->workspace) {
            $dispatcher->incrementUsage($workspace, 'messages_failed');

            $webhooks->dispatch($workspace, WebhookDispatcher::EVENT_MESSAGE_STATUS, [
                'message_id' => $message->id,
                'status' => 'failed',
                'error' => $error,
                'to' => $message->to_number,
            ]);
        }
    }
}
