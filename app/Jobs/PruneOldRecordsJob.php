<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\OtpCode;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Riwayat pesan dan log pengiriman webhook tumbuh sangat cepat pada gateway
 * yang sibuk. Retensi dijaga di sini; angka pemakaian tetap aman karena
 * usage_counters menyimpan agregat bulanan secara terpisah.
 */
class PruneOldRecordsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(): void
    {
        $messageCutoff = now()->subDays(config('gateway.retention.messages_days'));

        Message::where('created_at', '<', $messageCutoff)
            ->whereNotNull('media_path')
            ->chunkById(500, function ($messages): void {
                foreach ($messages as $message) {
                    Storage::disk('media')->delete($message->media_path);
                }
            });

        Message::where('created_at', '<', $messageCutoff)->delete();

        WebhookDelivery::where('created_at', '<', now()->subDays(config('gateway.retention.webhook_deliveries_days')))
            ->delete();

        // OTP tidak punya nilai setelah kedaluwarsa, dan menyimpannya lama
        // hanya menambah data nomor telepon yang tidak perlu ada.
        OtpCode::where('expires_at', '<', now()->subDay())->delete();
    }
}
