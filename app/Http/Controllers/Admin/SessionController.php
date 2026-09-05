<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WaSession;
use App\Services\SessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Seluruh sesi WhatsApp lintas workspace.
 *
 * Satu-satunya halaman di sistem ini yang sengaja TIDAK berangkat dari
 * workspace. Alasannya bukan kenyamanan: sesi adalah sumber daya bersama —
 * tiap sesi satu Chromium, dan seluruhnya berebut RAM container yang sama.
 * Saat kapasitas habis, yang dibutuhkan adalah melihat semuanya sekaligus dan
 * memadamkan sesi yang menganggur, bukan menebak lewat dashboard pelanggan
 * satu per satu.
 */
class SessionController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.sessions', [
            'sessions' => WaSession::query()
                ->with('workspace.subscription')
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                // CASE, bukan FIELD(): produksi memakai MySQL dan tes memakai
                // SQLite, dan FIELD() tidak ada di SQLite — halaman ini akan
                // hijau di produksi sambil menjatuhkan seluruh tesnya.
                ->orderByRaw("case status
                    when 'connected' then 1
                    when 'connecting' then 2
                    when 'qr' then 3
                    when 'pending' then 4
                    when 'failed' then 5
                    else 6 end")
                ->orderBy('updated_at', 'desc')
                ->paginate(40)
                ->withQueryString(),

            'status' => $status,
            'hidup' => WaSession::whereIn('status', ['connected', 'connecting', 'qr'])->count(),
            'kapasitas' => (int) config('gateway.engine.max_sessions'),
        ]);
    }

    /**
     * Memutus sesi dari panel.
     *
     * `disconnect()`, tidak pernah `logout()`. Logout membuang kredensial di
     * kedua sisi, jadi pemiliknya harus scan QR ulang — akibat yang terlalu
     * besar untuk sebuah tombol yang gunanya membebaskan memori. Setelah
     * diputus, sesi bisa dinyalakan lagi oleh pemiliknya tanpa scan.
     */
    public function disconnect(Request $request, string $id): RedirectResponse
    {
        $session = WaSession::findOrFail($id);

        try {
            $this->sessions->disconnect($session);
        } catch (\Throwable $e) {
            return back()->withErrors(['sesi' => 'Engine tidak menjawab: '.$e->getMessage()]);
        }

        AuditLog::record('admin.session_disconnected', $session, [
            'workspace' => $session->workspace?->name,
            'oleh' => $request->user()->email,
        ], $session->workspace_id);

        return back()->with('status', "Sesi '{$session->name}' diputus.");
    }
}
