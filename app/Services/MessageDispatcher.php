<?php

namespace App\Services;

use App\Jobs\SendMessageJob;
use App\Models\Message;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya pintu masuk pembuatan pesan keluar. Baik REST API, dashboard,
 * maupun OTP semuanya lewat sini supaya pencatatan, kuota, dan antrean
 * diperlakukan sama.
 */
class MessageDispatcher
{
    /**
     * @param  array{type?: string, body?: ?string, media_path?: ?string, media_mime?: ?string, media_filename?: ?string, batch_id?: ?string}  $attributes
     */
    public function queue(WaSession $session, string $to, array $attributes = []): Message
    {
        $workspace = $session->workspace;

        $this->guardWorkspace($workspace);

        $normalized = PhoneNumber::normalize($to);

        if ($normalized === null && ! str_ends_with($to, '@g.us')) {
            throw new RuntimeException("Nomor tujuan tidak valid: {$to}");
        }

        $message = Message::create([
            'workspace_id' => $workspace->id,
            'wa_session_id' => $session->id,
            'direction' => 'outbound',
            'chat_id' => PhoneNumber::toChatId($to),
            'to_number' => $normalized ?? $to,
            'type' => $attributes['type'] ?? 'text',
            'body' => $attributes['body'] ?? null,
            'media_path' => $attributes['media_path'] ?? null,
            'media_mime' => $attributes['media_mime'] ?? null,
            'media_filename' => $attributes['media_filename'] ?? null,
            'batch_id' => $attributes['batch_id'] ?? null,
            'status' => 'queued',
        ]);

        // Kuota dinaikkan saat antre, bukan saat terkirim: kalau dihitung
        // belakangan, satu workspace bisa mengantrekan puluhan ribu pesan dulu
        // lalu baru ketahuan melewati batas.
        $this->incrementUsage($workspace, 'messages_sent');

        SendMessageJob::dispatch($message->id);

        return $message;
    }

    /**
     * Mengantre banyak tujuan sekaligus. Semua pesan berbagi satu batch_id
     * supaya bisa ditelusuri dan dibatalkan sebagai satu kesatuan.
     *
     * @param  array<int, string>  $recipients
     * @return array{batch_id: string, messages: array<int, Message>, rejected: array<int, array{to: string, reason: string}>}
     */
    public function queueBulk(WaSession $session, array $recipients, array $attributes = []): array
    {
        $batchId = (string) Str::ulid();
        $messages = [];
        $rejected = [];

        foreach (array_unique($recipients) as $to) {
            try {
                $messages[] = $this->queue($session, $to, $attributes + ['batch_id' => $batchId]);
            } catch (RuntimeException $e) {
                // Satu nomor rusak tidak boleh menggagalkan seluruh broadcast;
                // pemanggil tetap diberi tahu nomor mana yang ditolak.
                $rejected[] = ['to' => $to, 'reason' => $e->getMessage()];
            }
        }

        return ['batch_id' => $batchId, 'messages' => $messages, 'rejected' => $rejected];
    }

    public function incrementUsage(Workspace $workspace, string $column, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        // upsert + increment mentah supaya aman dari race saat banyak worker
        // memproses pesan workspace yang sama secara bersamaan.
        DB::table('usage_counters')->upsert(
            [[
                'workspace_id' => $workspace->id,
                'period' => $period,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['workspace_id', 'period'],
            ['updated_at']
        );

        DB::table('usage_counters')
            ->where('workspace_id', $workspace->id)
            ->where('period', $period)
            ->increment($column, $amount);
    }

    private function guardWorkspace(Workspace $workspace): void
    {
        if (! $workspace->isActive()) {
            throw new RuntimeException('Workspace sedang tidak aktif.');
        }

        if (! $workspace->hasQuotaRemaining()) {
            throw new RuntimeException(
                "Kuota pesan bulan ini sudah habis ({$workspace->monthly_message_quota} pesan)."
            );
        }
    }
}
