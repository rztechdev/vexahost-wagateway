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
            ->where(function ($query): void {
                // Sesi yang belum pernah tersambung tidak ikut dijalankan
                // otomatis: ia hanya akan memunculkan QR yang tidak ada yang
                // men-scan.
                $query->whereIn('status', ['connected', 'connecting', 'disconnected'])
                    // Yang berstatus `failed` tapi pernah tersambung tetap
                    // dipulihkan: kredensialnya masih ada di volume, dan status
                    // itu paling sering tertinggal dari kegagalan sesaat —
                    // engine yang sedang di-deploy ulang, bukan tautan nomor
                    // yang benar-benar putus. Membiarkannya di luar daftar ini
                    // berarti memaksa scan ulang untuk masalah yang sudah lewat.
                    ->orWhere(fn ($q) => $q->where('status', 'failed')->whereNotNull('connected_at'));
            })
            ->get(['id', 'name', 'status']);

        return response()->json([
            'success' => true,
            'data' => $sessions->map(fn (WaSession $s) => [
                'session_id' => $s->id,
                'name' => $s->name,
                'last_known_status' => $s->status,
            ]),
        ]);
    }
}
