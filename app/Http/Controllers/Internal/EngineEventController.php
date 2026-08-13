<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\WaSession;
use App\Services\MessageDispatcher;
use App\Services\WebhookDispatcher;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Menerima seluruh kejadian dari engine Node: QR baru, sesi siap, sesi putus,
 * pesan masuk, dan perubahan status pengiriman.
 *
 * Endpoint ini yang membuat dashboard bisa menampilkan keadaan sebenarnya tanpa
 * menanyai engine terus-menerus.
 */
class EngineEventController extends Controller
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
        private readonly MessageDispatcher $dispatcher,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string'],
            'event' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $session = WaSession::with('tenant')->find($data['session_id']);

        if (! $session) {
            // Sesi sudah dihapus di dashboard tapi engine belum tahu. Balas 200
            // supaya engine berhenti mengulang, dan beri tahu agar dimatikan.
            return response()->json(['success' => true, 'action' => 'stop_session']);
        }

        $payload = $data['payload'] ?? [];

        match ($data['event']) {
            'qr' => $this->onQr($session, $payload),
            'authenticated' => $session->update(['status' => 'connecting', 'qr_payload' => null]),
            'ready' => $this->onReady($session, $payload),
            'disconnected' => $this->onDisconnected($session, $payload),
            'auth_failure' => $this->onAuthFailure($session, $payload),
            'message' => $this->onIncomingMessage($session, $payload),
            'message_ack' => $this->onAck($session, $payload),
            default => Log::info('Event engine tidak dikenal', ['event' => $data['event']]),
        };

        return response()->json(['success' => true]);
    }

    private function onQr(WaSession $session, array $payload): void
    {
        $session->update([
            'status' => 'qr',
            // Sudah berupa data URI PNG dari engine.
            'qr_payload' => $payload['qr_image'] ?? null,
            // whatsapp-web.js menerbitkan QR baru dengan jeda tidak tetap —
            // pengamatan menunjukkan 20 sampai 60 detik. Masa berlaku harus
            // lebih panjang dari jeda terlama itu, kalau tidak akan ada celah
            // di mana QR dianggap kedaluwarsa padahal penggantinya belum
            // datang, dan modal di dashboard mendadak kosong saat pengguna
            // sedang bersiap men-scan. QR yang basi bukan masalah: begitu event
            // berikutnya tiba, nilainya langsung ditimpa.
            'qr_expires_at' => now()->addSeconds(config('gateway.qr_ttl_seconds')),
        ]);

        $this->webhooks->dispatch($session->tenant, WebhookDispatcher::EVENT_SESSION_QR, [
            'session_id' => $session->id,
            'status' => 'qr',
        ]);
    }

    private function onReady(WaSession $session, array $payload): void
    {
        $session->update([
            'status' => 'connected',
            'phone_number' => PhoneNumber::normalize($payload['phone_number'] ?? null),
            'push_name' => $payload['push_name'] ?? null,
            'qr_payload' => null,
            'qr_expires_at' => null,
            'connected_at' => now(),
            'last_seen_at' => now(),
            'last_error' => null,
        ]);

        $this->notifySessionStatus($session, 'connected');
    }

    private function onDisconnected(WaSession $session, array $payload): void
    {
        $session->update([
            'status' => 'disconnected',
            'qr_payload' => null,
            'last_error' => $payload['reason'] ?? null,
        ]);

        $this->notifySessionStatus($session, 'disconnected');
    }

    private function onAuthFailure(WaSession $session, array $payload): void
    {
        $session->update([
            'status' => 'failed',
            'qr_payload' => null,
            'last_error' => $payload['message'] ?? 'Autentikasi WhatsApp gagal.',
        ]);

        $this->notifySessionStatus($session, 'failed');
    }

    private function onIncomingMessage(WaSession $session, array $payload): void
    {
        $message = Message::create([
            'tenant_id' => $session->tenant_id,
            'wa_session_id' => $session->id,
            'direction' => 'inbound',
            'wa_message_id' => $payload['wa_message_id'] ?? null,
            'chat_id' => $payload['chat_id'] ?? null,
            'from_number' => PhoneNumber::normalize($payload['from'] ?? null),
            'to_number' => $session->phone_number,
            'type' => $payload['type'] ?? 'text',
            'body' => $payload['body'] ?? null,
            'status' => 'delivered',
        ]);

        $this->dispatcher->incrementUsage($session->tenant, 'messages_received');

        $this->webhooks->dispatch($session->tenant, WebhookDispatcher::EVENT_MESSAGE_RECEIVED, [
            'message_id' => $message->id,
            'session_id' => $session->id,
            'from' => $message->from_number,
            'chat_id' => $message->chat_id,
            'type' => $message->type,
            'body' => $message->body,
            'is_group' => str_ends_with((string) $message->chat_id, '@g.us'),
            'received_at' => $message->created_at->toIso8601String(),
        ]);
    }

    /**
     * WhatsApp mengirim ack bertingkat: 1 terkirim ke server, 2 sampai ke HP
     * tujuan, 3 dibaca. Ack bisa datang tidak berurutan, jadi Message
     * memastikan status hanya boleh maju.
     */
    private function onAck(WaSession $session, array $payload): void
    {
        $message = Message::where('wa_session_id', $session->id)
            ->where(function ($q) use ($payload): void {
                $q->where('id', $payload['message_id'] ?? null)
                    ->orWhere('wa_message_id', $payload['wa_message_id'] ?? null);
            })
            ->first();

        if (! $message) {
            return;
        }

        $status = match ((int) ($payload['ack'] ?? 0)) {
            1 => 'sent',
            2 => 'delivered',
            3, 4 => 'read',
            default => null,
        };

        if ($status === null || ! $message->advanceStatus($status)) {
            return;
        }

        $message->forceFill([
            'delivered_at' => $status === 'delivered' ? now() : $message->delivered_at,
            'read_at' => $status === 'read' ? now() : $message->read_at,
        ])->save();

        $this->webhooks->dispatch($session->tenant, WebhookDispatcher::EVENT_MESSAGE_STATUS, [
            'message_id' => $message->id,
            'status' => $status,
            'to' => $message->to_number,
        ]);
    }

    private function notifySessionStatus(WaSession $session, string $status): void
    {
        $this->webhooks->dispatch($session->tenant, WebhookDispatcher::EVENT_SESSION_STATUS, [
            'session_id' => $session->id,
            'name' => $session->name,
            'status' => $status,
            'phone_number' => $session->phone_number,
        ]);
    }
}
