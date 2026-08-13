<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\SessionBackup;
use App\Models\WaSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyimpanan backup sesi untuk RemoteAuth milik whatsapp-web.js.
 *
 * Ini inti penyelesaian masalah "harus scan QR ulang tiap redeploy": engine
 * menyimpan kredensial sesi di persistent volume, dan secara berkala mengirim
 * zip-nya ke sini. Kalau volume ikut hilang (server diganti, volume terhapus),
 * engine menarik zip terakhir dari sini saat boot dan langsung tersambung lagi.
 */
class SessionBackupController extends Controller
{
    private const DISK = 'session-backups';

    public function store(Request $request, string $sessionId): JsonResponse
    {
        $session = WaSession::find($sessionId);

        if (! $session) {
            return response()->json(['success' => false, 'error' => ['message' => 'Sesi tidak ditemukan.']], 404);
        }

        $path = "{$session->id}/session.zip";
        $tmp = tempnam(sys_get_temp_dir(), 'wa-backup-');

        // Ditulis streaming ke file sementara supaya zip berukuran besar tidak
        // pernah dimuat utuh ke memori PHP.
        $in = fopen('php://input', 'rb');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $checksum = hash_file('sha256', $tmp);
        $claimed = $request->header('X-Engine-Body-Sha256');

        if ($claimed && ! hash_equals($claimed, $checksum)) {
            @unlink($tmp);

            return response()->json([
                'success' => false,
                'error' => ['message' => 'Isi backup tidak cocok dengan checksum yang ditandatangani.'],
            ], 422);
        }

        Storage::disk(self::DISK)->put($path, fopen($tmp, 'rb'));
        $size = filesize($tmp);
        @unlink($tmp);

        // Satu baris per sesi: yang dibutuhkan hanya backup terbaru, dan
        // menyimpan riwayat zip lama justru memenuhi disk tanpa guna.
        SessionBackup::updateOrCreate(
            ['wa_session_id' => $session->id],
            [
                'disk' => self::DISK,
                'path' => $path,
                'size' => $size,
                'checksum' => $checksum,
                'backed_up_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => ['size' => $size, 'checksum' => $checksum]]);
    }

    public function show(string $sessionId): StreamedResponse|JsonResponse
    {
        $backup = SessionBackup::where('wa_session_id', $sessionId)->first();

        if (! $backup || ! Storage::disk($backup->disk)->exists($backup->path)) {
            return response()->json(['success' => false, 'error' => ['message' => 'Backup tidak ada.']], 404);
        }

        return Storage::disk($backup->disk)->download($backup->path, 'session.zip', [
            'X-Backup-Sha256' => $backup->checksum,
        ]);
    }

    public function exists(string $sessionId): JsonResponse
    {
        $backup = SessionBackup::where('wa_session_id', $sessionId)->first();

        $exists = $backup !== null && Storage::disk($backup->disk)->exists($backup->path);

        return response()->json([
            'success' => true,
            'data' => [
                'exists' => $exists,
                'backed_up_at' => $backup?->backed_up_at?->toIso8601String(),
                'checksum' => $backup?->checksum,
            ],
        ]);
    }

    public function destroy(string $sessionId): JsonResponse
    {
        $backup = SessionBackup::where('wa_session_id', $sessionId)->first();

        if ($backup) {
            Storage::disk($backup->disk)->delete($backup->path);
            $backup->delete();
        }

        return response()->json(['success' => true]);
    }
}
