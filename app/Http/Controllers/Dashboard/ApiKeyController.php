<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.api-keys.index', [
            'keys' => EnsureWorkspaceSelected::from($request)->apiKeys()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:40'],
        ]);

        $workspace = EnsureWorkspaceSelected::from($request);

        [$key, $plain] = ApiKey::issue(
            $workspace,
            $data['name'],
            $data['scopes'] ?: ['*'],
            $request->user()->id,
        );

        AuditLog::record('api_key.created', $key, ['name' => $key->name]);

        // Nilai polos hanya lewat flash session, tidak pernah tersimpan.
        return back()->with('new_api_key', $plain)->with('status', 'API key dibuat. Salin sekarang — nilai ini tidak bisa dilihat lagi.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $key = EnsureWorkspaceSelected::from($request)->apiKeys()->findOrFail($id);

        // Dicabut, bukan dihapus: baris tetap ada supaya jejak "kunci ini pernah
        // dipakai sampai tanggal sekian" tidak ikut hilang.
        $key->update(['revoked_at' => now()]);

        AuditLog::record('api_key.revoked', $key, ['name' => $key->name]);

        return back()->with('status', "API key '{$key->name}' dicabut.");
    }
}
