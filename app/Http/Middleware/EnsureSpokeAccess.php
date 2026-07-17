<?php

namespace App\Http\Middleware;

use App\Core\Spokes\SpokeRegistry;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Ensures the active tenant has an active subscription to the Spoke being
 * accessed before the request reaches the component.
 *
 * Route registration example:
 *   Route::middleware(['auth', 'tenant.context', 'spoke.access:docs'])
 *       ->prefix('app/docs')
 *       ...
 */
class EnsureSpokeAccess
{
    public function __construct(private SpokeRegistry $registry) {}

    public function handle(Request $request, Closure $next, string $spokeSlug): Response
    {
        $tenant = app()->bound('current.tenant') ? app('current.tenant') : null;

        if (! $tenant instanceof Tenant) {
            throw new AccessDeniedHttpException('No active tenant context.');
        }

        $spoke = $this->registry->findBySlug($spokeSlug);

        if ($spoke === null) {
            abort(404, "Spoke [{$spokeSlug}] not found.");
        }

        if (! $tenant->hasSpokeAccess($spoke)) {
            abort(403, 'Your tenant does not have access to this Spoke.');
        }

        // Bind the resolved Spoke so downstream code (components, views) can
        // reference it without re-querying.
        app()->instance('current.spoke', $spoke);

        return $next($request);
    }
}
