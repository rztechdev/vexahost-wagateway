<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\OtpCode;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
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
        // Retensi berbeda per paket — itu salah satu yang dibeli pelanggan saat
        // naik ke paket lebih tinggi. Karena itu penyapuan berjalan per
        // workspace, bukan sekali dengan satu batas untuk semua: satu angka
        // global akan menghapus riwayat 12 bulan milik pelanggan Elite.
        Workspace::withTrashed()->select(['id', 'is_internal', 'plan_slug'])
            ->with('subscription:id,workspace_id,plan_slug')
            ->chunkById(100, function ($workspaces): void {
                foreach ($workspaces as $workspace) {
                    $this->pruneMessages(
                        $workspace->id,
                        now()->subDays($workspace->messageRetentionDays()),
                    );
                }
            });

        WebhookDelivery::where('created_at', '<', now()->subDays(config('gateway.retention.webhook_deliveries_days')))
            ->delete();

        // OTP tidak punya nilai setelah kedaluwarsa, dan menyimpannya lama
        // hanya menambah data nomor telepon yang tidak perlu ada.
        OtpCode::where('expires_at', '<', now()->subDay())->delete();
    }

    /**
     * Berkas media dihapus lebih dulu, satu per satu, baru barisnya.
     * Menghapus baris duluan berarti kehilangan satu-satunya petunjuk berkas
     * mana yang harus dibuang — dan disk terisi oleh lampiran yang tidak lagi
     * ditunjuk apa pun, tanpa gejala sampai disk penuh.
     */
    private function pruneMessages(int $workspaceId, \DateTimeInterface $cutoff): void
    {
        Message::where('workspace_id', $workspaceId)
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('media_path')
            ->chunkById(500, function ($messages): void {
                foreach ($messages as $message) {
                    Storage::disk('media')->delete($message->media_path);
                }
            });

        Message::where('workspace_id', $workspaceId)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
