<?php

namespace App\Services;

use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Providers\ProviderManager;
use RuntimeException;

class SessionService
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function create(Workspace $workspace, string $name, string $driver = 'wwebjs'): WaSession
    {
        if (! $workspace->canAddSession()) {
            throw new RuntimeException(
                "Workspace sudah memakai seluruh jatah sesi ({$workspace->max_sessions})."
            );
        }

        // Sesi dihapus lunak, tapi indeks unik (workspace_id, name) tidak peduli
        // deleted_at — tanpa ini, memakai ulang nama sesi yang sudah dihapus
        // gagal dengan galat duplikat yang tidak bisa dipahami pengguna.
        // Dihapus permanen, bukan dipulihkan: baris baru mendapat ULID baru,
        // sehingga tidak mewarisi folder kredensial lama di engine kalau
        // logout sempat gagal saat penghapusan.
        $workspace->sessions()->onlyTrashed()->where('name', $name)->get()
            ->each(fn (WaSession $stale) => $stale->forceDelete());

        return $workspace->sessions()->create([
            'name' => $name,
            'driver' => $driver,
            'status' => 'pending',
        ]);
    }

    public function connect(WaSession $session): WaSession
    {
        $session->update([
            'status' => 'connecting',
            'last_error' => null,
        ]);

        try {
            $this->providers->for($session)->startSession($session);
        } catch (\Throwable $e) {
            $session->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

            throw $e;
        }

        return $session->refresh();
    }

    public function disconnect(WaSession $session): WaSession
    {
        $this->providers->for($session)->stopSession($session);

        $session->update(['status' => 'disconnected', 'qr_payload' => null]);

        return $session;
    }

    /**
     * Memutus tautan perangkat sepenuhnya. Berbeda dari disconnect: kredensial
     * dibuang di kedua sisi, jadi menghubungkan lagi berarti scan QR baru.
     */
    public function logout(WaSession $session): WaSession
    {
        $this->providers->for($session)->logoutSession($session);

        $session->update([
            'status' => 'disconnected',
            'qr_payload' => null,
            'phone_number' => null,
            'push_name' => null,
            'connected_at' => null,
        ]);

        $session->backups()->delete();

        return $session;
    }

    /**
     * Menyelaraskan status di database dengan keadaan sebenarnya di engine.
     * Dipanggil terjadwal karena engine bisa saja mati tanpa sempat mengirim
     * callback disconnected (mis. container dibunuh).
     */
    public function syncStatus(WaSession $session): WaSession
    {
        $state = $this->providers->for($session)->status($session);

        $session->update([
            'status' => $state['status'],
            'phone_number' => $state['phone_number'] ?? $session->phone_number,
            'push_name' => $state['push_name'] ?? $session->push_name,
            'last_seen_at' => now(),
        ]);

        return $session;
    }
}
