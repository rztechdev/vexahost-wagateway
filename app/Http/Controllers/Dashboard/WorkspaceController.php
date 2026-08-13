<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(private readonly SessionService $sessions) {}

    public function settings(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.settings', [
            'members' => $workspace->members()->orderBy('name')->get(),
            'usage' => $workspace->currentUsage(),
            'history' => $workspace->usageCounters()->orderByDesc('period')->limit(12)->get(),
        ]);
    }

    public function switch(Request $request, int $id): RedirectResponse
    {
        // Hanya workspace yang benar-benar diikuti user yang boleh dipilih —
        // tanpa cek ini, mengganti id di URL sama saja membuka data orang lain.
        $workspace = $request->user()->workspaces()->findOrFail($id);

        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard');
    }

    public function createForm(): View
    {
        return view('dashboard.onboarding');
    }

    public function create(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $user = $request->user();

        $workspace = Workspace::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => config('gateway.defaults.max_sessions'),
            'monthly_message_quota' => config('gateway.defaults.monthly_message_quota'),
            'api_rate_limit_per_minute' => config('gateway.defaults.api_rate_limit_per_minute'),
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        session(['current_workspace_id' => $workspace->id]);

        AuditLog::record('workspace.created', $workspace, ['name' => $workspace->name], $workspace->id);

        return redirect()->route('sessions.index')
            ->with('status', 'Workspace dibuat. Langkah berikutnya: buat sesi dan scan QR.');
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403, 'Hanya owner atau admin yang bisa mengubah workspace.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        // Slug sengaja tidak ikut berubah. Ia dipakai sebagai pengenal yang
        // tahan lama di log dan audit; nama boleh berubah kapan saja, slug
        // tidak. Tidak ada satu pun URL atau API key yang bergantung padanya.
        $workspace->update(['name' => $data['name']]);

        AuditLog::record('workspace.renamed', $workspace, ['name' => $data['name']], $workspace->id);

        return back()->with('status', 'Nama workspace diperbarui.');
    }

    /**
     * Menghapus workspace beserta seluruh isinya.
     *
     * Diminta mengetik ulang namanya, bukan sekadar mengklik "yakin?": yang
     * hilang di sini bukan cuma satu baris, tapi seluruh riwayat pesan, API
     * key, dan tautan nomor — dan aplikasi yang memegang API key-nya akan
     * berhenti bisa mengirim tanpa pesan galat yang menjelaskan kenapa.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);
        $user = $request->user();

        if (! $user->is_super_admin && $user->roleIn($workspace) !== 'owner') {
            abort(403, 'Hanya owner yang bisa menghapus workspace.');
        }

        $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if ($request->input('confirm') !== $workspace->name) {
            return back()->withErrors([
                'confirm' => 'Nama yang diketik tidak cocok. Workspace tidak jadi dihapus.',
            ]);
        }

        // Sesi dihentikan lebih dulu supaya Chromium-nya benar-benar mati di
        // engine. Tanpa ini, engine tetap memegang koneksi ke nomor yang
        // workspace-nya sudah tidak ada — memakan memori sampai engine
        // di-restart, dan pesan masuk tetap ditembakkan ke Laravel.
        foreach ($workspace->sessions as $session) {
            try {
                $this->sessions->logout($session);
            } catch (\Throwable) {
                // Engine tidak terjangkau. Penghapusan tetap diteruskan —
                // membiarkan workspace hidup hanya karena engine sedang
                // bermasalah justru membuat pengguna terjebak.
            }
        }

        $nama = $workspace->name;

        AuditLog::record('workspace.deleted', $workspace, ['name' => $nama], $workspace->id);

        $workspace->delete();
        session()->forget('current_workspace_id');

        return redirect()->route('dashboard')->with('status', "Workspace '{$nama}' dihapus.");
    }

    public function addMember(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403, 'Hanya owner atau admin yang bisa menambah anggota.');
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        // Anggota harus sudah punya akun Flustra ID: gateway tidak membuat
        // identitas baru, itu wewenang flustra-auth.
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'Belum ada pengguna dengan email ini. Minta mereka login ke '
                    .config('app.url').' sekali dulu lewat Flustra ID.',
            ]);
        }

        $workspace->members()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);

        AuditLog::record('workspace.member_added', $user, ['email' => $user->email], $workspace->id);

        return back()->with('status', "{$user->name} ditambahkan sebagai {$data['role']}.");
    }

    public function removeMember(Request $request, int $userId): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        if (! $request->user()->canManage($workspace)) {
            abort(403);
        }

        $role = $workspace->members()->where('users.id', $userId)->first()?->pivot->role;

        if ($role === 'owner') {
            return back()->withErrors(['member' => 'Owner tidak bisa dikeluarkan dari workspace-nya sendiri.']);
        }

        $workspace->members()->detach($userId);

        return back()->with('status', 'Anggota dikeluarkan.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (Workspace::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
