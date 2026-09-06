<?php

namespace App\Jobs;

use App\Models\WaSession;
use App\Services\SessionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Menyelaraskan status sesi dengan keadaan sebenarnya di engine, lalu
 * menyambungkan ulang sesi yang putus. Callback dari engine sudah menangani
 * kasus normal; job ini menutup kasus engine mati mendadak sehingga tidak
 * sempat mengabari siapa pun.
 */
class SyncSessionStatusJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function handle(SessionService $sessions): void
    {
        $candidates = WaSession::query()
            ->with('workspace')
            ->whereIn('status', ['connected', 'connecting', 'disconnected'])
            ->where('driver', 'wwebjs')
            ->get();

        foreach ($candidates as $session) {
            try {
                $before = $session->status;
                $session = $sessions->syncStatus($session);

                if ($before === 'connected' && $session->status !== 'connected') {
                    Log::warning('Sesi WhatsApp terputus', ['session_id' => $session->id]);
                }

                /*
                 | Menyelaraskan status boleh untuk sesi mana pun — itu cuma
                 | membaca. Yang TIDAK boleh adalah menjalankannya lagi untuk
                 | workspace yang layanannya sudah mati: `releaseSessions()`
                 | melepasnya setelah masa tenggang, lalu job ini menjalankannya
                 | lagi satu menit kemudian, tiap menit, selamanya. Penegakan
                 | langganan yang bisa dibatalkan oleh penjadwal kita sendiri
                 | bukan penegakan.
                */
                if ($session->status === 'disconnected'
                    && $session->auto_reconnect
                    && $session->workspace?->isActive()) {
                    $sessions->connect($session);
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal menyelaraskan status sesi', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
