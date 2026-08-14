<?php

namespace App\Http\Controllers\Api;

use App\Models\WaSession;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends ApiController
{
    public function __construct(private readonly SessionService $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->workspace($request)->sessions()->orderBy('name')->get();

        return $this->ok($sessions->map(fn (WaSession $s) => $this->present($s)));
    }

    public function store(Request $request): JsonResponse
    {
        // `driver` sengaja tidak lagi diterima. Hanya ada satu cara mengirim di
        // produk ini, jadi menerimanya berarti menjanjikan pilihan yang tidak
        // ada — dan integrasi yang terlanjur mengirimkannya akan menyangka
        // pilihannya dihormati.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $workspace = $this->workspace($request);

        try {
            $session = $this->sessions->create($workspace, $data['name']);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->present($session), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->ok($this->present($this->find($request, $id)));
    }

    public function connect(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        try {
            $session = $this->sessions->connect($session);
        } catch (\Throwable $e) {
            return $this->fail('Gagal menghubungkan sesi: '.$e->getMessage(), 502);
        }

        return $this->ok($this->present($session));
    }

    public function disconnect(Request $request, string $id): JsonResponse
    {
        return $this->ok($this->present($this->sessions->disconnect($this->find($request, $id))));
    }

    public function logout(Request $request, string $id): JsonResponse
    {
        return $this->ok($this->present($this->sessions->logout($this->find($request, $id))));
    }

    /**
     * QR sudah disimpan sebagai data URI PNG (dirender engine dengan paket
     * `qrcode`), jadi klien bisa langsung menaruhnya di <img src>.
     */
    public function qr(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        if ($session->isConnected()) {
            return $this->ok(['status' => 'connected', 'qr' => null]);
        }

        if (! $session->hasFreshQr()) {
            return $this->ok(['status' => $session->status, 'qr' => null]);
        }

        return $this->ok([
            'status' => 'qr',
            'qr' => $session->qr_payload,
            'expires_at' => $session->qr_expires_at->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        // Selalu putuskan tautan dulu: menghapus baris tanpa logout meninggalkan
        // perangkat tertaut di HP pemilik nomor tanpa cara mencabutnya dari sini.
        try {
            $this->sessions->logout($session);
        } catch (\Throwable) {
            // Engine mati bukan alasan untuk menolak penghapusan.
        }

        $session->delete();

        return $this->ok(null, 204);
    }

    private function find(Request $request, string $id): WaSession
    {
        return $this->workspace($request)->sessions()->findOrFail($id);
    }

    private function present(WaSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'driver' => $session->driver,
            'status' => $session->status,
            'phone_number' => $session->phone_number,
            'push_name' => $session->push_name,
            'auto_reconnect' => $session->auto_reconnect,
            'connected_at' => $session->connected_at?->toIso8601String(),
            'last_seen_at' => $session->last_seen_at?->toIso8601String(),
            'last_error' => $session->last_error,
        ];
    }
}
