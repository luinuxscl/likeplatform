<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the current request and binds it into the
 * container so that downstream code (controllers, components, gates) can
 * access it via `app('current.tenant')` or `$user->activeTenant()`.
 *
 * Resolution order:
 *   1. `current_tenant_id` in the session (set by the App Switcher)
 *   2. `{tenantSlug}` parameter when the route definition is `/{tenant}/...`
 *
 * Once resolved, calls `Spatie\Permission\PermissionRegistrar::setPermissionsTeamId()`
 * so that role/permission lookups via the `HasRoles` trait are automatically
 * scoped to the active tenant.
 */
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant !== null) {
            $this->bindTenant($tenant);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        // 1. Session-based resolution (App Switcher)
        if ($request->hasSession()) {
            $tenantId = $request->session()->get('current_tenant_id');

            if ($tenantId !== null) {
                return Tenant::active()->where('id', $tenantId)->first();
            }
        }

        // 2. Path-based resolution fallback (deep links, /t/{slug}/...)
        $tenantSlug = $request->route('tenant');

        if ($tenantSlug !== null) {
            return Tenant::active()->where('slug', $tenantSlug)->first();
        }

        return null;
    }

    private function bindTenant(Tenant $tenant): void
    {
        app()->instance('current.tenant', $tenant);
        $request = request();

        if ($request->hasSession()) {
            $request->session()->put('current_tenant_id', $tenant->id);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    }
}
