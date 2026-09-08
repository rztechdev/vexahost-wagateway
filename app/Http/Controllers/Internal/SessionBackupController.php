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
 * Penyimpanan backup sesi untuk autentikasi Baileys.
 *
 * Menggantikan format zip RemoteAuth milik whatsapp-web.js. Kredensial Baileys
 * berupa berkas JSON (creds.json dan berkas kunci) yang dibundel menjadi satu
 * payload JSON terstruktur, ditandatangani checksum SHA-256, dan disimpan di sini.
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

        $path = "{$session->id}/session.json";
        $tmp = tempnam(sys_get_temp_dir(), 'wa-backup-');

        try {
            $stream = $request->getContent(true);
            $out = fopen($tmp, 'wb');
            if (is_resource($stream)) {
                stream_copy_to_stream($stream, $out);
            } else {
                $in = fopen('php://input', 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);

            $checksum = hash_file('sha256', $tmp);
            $claimed = $request->header('X-Engine-Body-Sha256');

            if ($claimed && ! hash_equals($claimed, $checksum)) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Isi backup tidak cocok dengan checksum yang ditandatangani.'],
                ], 422);
            }

            $content = file_get_contents($tmp);
            if (! json_validate($content)) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Isi backup bukan JSON yang valid.'],
                ], 422);
            }

            Storage::disk(self::DISK)->put($path, $content);
            $size = strlen($content);

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
        } finally {
            @unlink($tmp);
        }
    }

    public function show(string $sessionId): StreamedResponse|JsonResponse
    {
        $backup = SessionBackup::where('wa_session_id', $sessionId)->first();

        if (! $backup || ! Storage::disk($backup->disk)->exists($backup->path)) {
            return response()->json(['success' => false, 'error' => ['message' => 'Backup tidak ada.']], 404);
        }

        return Storage::disk($backup->disk)->download($backup->path, 'session.json', [
            'Content-Type' => 'application/json',
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
