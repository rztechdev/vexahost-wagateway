<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\WaSession;
use Illuminate\Http\JsonResponse;

/**
 * Dipanggil engine sekali saat boot. Engine sendiri tidak menyimpan daftar
 * sesi — daftar itu ada di sini — sehingga container engine sepenuhnya bisa
 * dibuang dan dibangun ulang tanpa kehilangan apa pun.
 */
class BootstrapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $sessions = WaSession::query()
            ->where('driver', 'wwebjs')
            ->where('auto_reconnect', true)
            // Sesi yang belum pernah tersambung tidak ikut dijalankan otomatis:
            // ia hanya akan memunculkan QR yang tidak ada yang men-scan.
            ->whereIn('status', ['connected', 'connecting', 'disconnected'])
            ->get(['id', 'name', 'kind', 'status']);

        return response()->json([
            'success' => true,
            'data' => $sessions->map(fn (WaSession $s) => [
                'session_id' => $s->id,
                'name' => $s->name,
                'kind' => $s->kind,
                'last_known_status' => $s->status,
            ]),
        ]);
    }
}
