<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\AuditLog;
use App\Models\WaSession;
use App\Services\SessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function index(Request $request): View
    {
        return view('dashboard.sessions.index', [
            'sessions' => EnsureWorkspaceSelected::from($request)->sessions()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'driver' => ['required', Rule::in(['wwebjs', 'cloud_api', 'fonnte'])],
        ]);

        $workspace = EnsureWorkspaceSelected::from($request);

        try {
            $session = $this->sessions->create($workspace, $data['name'], $data['driver']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['name' => $e->getMessage()])->withInput();
        }

        AuditLog::record('session.created', $session, ['name' => $session->name]);

        return redirect()->route('sessions.index')
            ->with('status', "Sesi '{$session->name}' dibuat. Klik Hubungkan lalu scan QR-nya.");
    }

    public function connect(Request $request, string $id): RedirectResponse
    {
        $session = $this->find($request, $id);

        try {
            $this->sessions->connect($session);
        } catch (\Throwable $e) {
            return back()->withErrors(['session' => 'Gagal menghubungi engine: '.$e->getMessage()]);
        }

        AuditLog::record('session.connect', $session);

        return back()->with('open_qr', $session->id);
    }

    public function disconnect(Request $request, string $id): RedirectResponse
    {
        $session = $this->find($request, $id);

        $this->sessions->disconnect($session);
        AuditLog::record('session.disconnect', $session);

        return back()->with('status', 'Sesi dihentikan.');
    }

    public function logout(Request $request, string $id): RedirectResponse
    {
        $session = $this->find($request, $id);

        $this->sessions->logout($session);
        AuditLog::record('session.logout', $session);

        return back()->with(
            'status',
            'Tautan nomor diputus. Klik Hubungkan untuk scan QR lagi — boleh dengan nomor yang sama atau nomor lain.'
        );
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $session = $this->find($request, $id);

        try {
            $this->sessions->logout($session);
        } catch (\Throwable) {
            // Engine tidak bisa dihubungi — baris tetap dihapus supaya dashboard
            // tidak menyisakan sesi hantu yang tidak bisa diapa-apakan.
        }

        AuditLog::record('session.deleted', $session, ['name' => $session->name]);
        $session->delete();

        return redirect()->route('sessions.index')->with('status', 'Sesi dihapus.');
    }

    /**
     * Dipanggil modal QR setiap 3 detik. Sengaja polling, bukan websocket:
     * satu request kecil tiap 3 detik hanya saat modal terbuka jauh lebih murah
     * daripada menjalankan Reverb khusus untuk ini.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $session = $this->find($request, $id);

        return response()->json([
            'status' => $session->status,
            'qr' => $session->hasFreshQr() ? $session->qr_payload : null,
            'phone_number' => $session->phone_number,
            'push_name' => $session->push_name,
            'error' => $session->last_error,
        ]);
    }

    private function find(Request $request, string $id): WaSession
    {
        return EnsureWorkspaceSelected::from($request)->sessions()->findOrFail($id);
    }
}
