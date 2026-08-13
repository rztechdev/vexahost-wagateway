<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan tenant yang sedang dibuka di dashboard dan menaruhnya di request.
 * Semua controller dashboard mengambilnya dari sini, jadi tidak ada satu pun
 * query yang perlu memilih tenant sendiri — sekaligus mencegah tenant A
 * membaca data tenant B.
 */
class EnsureTenantSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $tenants = $user->tenants()->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            return redirect()->route('onboarding.create');
        }

        $selectedId = session('current_tenant_id');
        $tenant = $tenants->firstWhere('id', $selectedId) ?? $tenants->first();

        session(['current_tenant_id' => $tenant->id]);

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenants', $tenants);

        view()->share('currentTenant', $tenant);
        view()->share('availableTenants', $tenants);

        return $next($request);
    }

    public static function from(Request $request): Tenant
    {
        return $request->attributes->get('tenant');
    }
}
