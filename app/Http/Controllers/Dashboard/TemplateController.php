<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantSelected;
use App\Models\MessageTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.templates.index', [
            'templates' => EnsureTenantSelected::from($request)->templates()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = EnsureTenantSelected::from($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('message_templates')->where('tenant_id', $tenant->id)],
            'body' => ['required', 'string', 'max:4096'],
        ]);

        $template = $tenant->templates()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'body' => $data['body'],
            // Selalu diturunkan dari isi body, bukan diisi manual, supaya
            // daftar variabel tidak pernah bisa melenceng dari templatenya.
            'variables' => MessageTemplate::extractVariables($data['body']),
        ]);

        return back()->with('status', "Template '{$template->name}' disimpan.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $template = EnsureTenantSelected::from($request)->templates()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'name' => $data['name'],
            'body' => $data['body'],
            'variables' => MessageTemplate::extractVariables($data['body']),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Template diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        EnsureTenantSelected::from($request)->templates()->findOrFail($id)->delete();

        return back()->with('status', 'Template dihapus.');
    }
}
