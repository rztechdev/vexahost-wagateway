<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantSelected;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function settings(Request $request): View
    {
        $tenant = EnsureTenantSelected::from($request);

        return view('dashboard.settings', [
            'members' => $tenant->members()->orderBy('name')->get(),
            'usage' => $tenant->currentUsage(),
            'history' => $tenant->usageCounters()->orderByDesc('period')->limit(12)->get(),
        ]);
    }

    public function switch(Request $request, int $id): RedirectResponse
    {
        // Hanya tenant yang benar-benar diikuti user yang boleh dipilih —
        // tanpa cek ini, mengganti id di URL sama saja membuka data orang lain.
        $tenant = $request->user()->tenants()->findOrFail($id);

        session(['current_tenant_id' => $tenant->id]);

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

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'owner_sso_id' => $user->sso_id,
            'owner_email' => $user->email,
            'max_sessions' => config('gateway.defaults.max_sessions'),
            'monthly_message_quota' => config('gateway.defaults.monthly_message_quota'),
            'api_rate_limit_per_minute' => config('gateway.defaults.api_rate_limit_per_minute'),
        ]);

        $tenant->members()->attach($user->id, ['role' => 'owner']);

        session(['current_tenant_id' => $tenant->id]);

        AuditLog::record('tenant.created', $tenant, ['name' => $tenant->name], $tenant->id);

        return redirect()->route('sessions.index')
            ->with('status', 'Workspace dibuat. Langkah berikutnya: buat sesi dan scan QR.');
    }

    public function addMember(Request $request): RedirectResponse
    {
        $tenant = EnsureTenantSelected::from($request);

        if (! $request->user()->canManage($tenant)) {
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

        $tenant->members()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);

        AuditLog::record('tenant.member_added', $user, ['email' => $user->email], $tenant->id);

        return back()->with('status', "{$user->name} ditambahkan sebagai {$data['role']}.");
    }

    public function removeMember(Request $request, int $userId): RedirectResponse
    {
        $tenant = EnsureTenantSelected::from($request);

        if (! $request->user()->canManage($tenant)) {
            abort(403);
        }

        $role = $tenant->members()->where('users.id', $userId)->first()?->pivot->role;

        if ($role === 'owner') {
            return back()->withErrors(['member' => 'Owner tidak bisa dikeluarkan dari workspace-nya sendiri.']);
        }

        $tenant->members()->detach($userId);

        return back()->with('status', 'Anggota dikeluarkan.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
