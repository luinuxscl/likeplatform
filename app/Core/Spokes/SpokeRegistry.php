<?php

namespace App\Core\Spokes;

use App\Models\Spoke;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * In-memory catalogue of every Spoke available on the platform.
 *
 * Registered as a singleton in the container so that the list is loaded once
 * per request. Downstream code (middleware, AppSwitcher, Hub dashboard) calls
 * `resolveTenantSpokes()` to filter the catalogue to only the Spokes the
 * active tenant has subscribed to.
 */
class SpokeRegistry
{
    /** @var Collection<int, Spoke>|null */
    private ?Collection $catalogue = null;

    /**
     * All active Spokes ordered by their sort position.
     *
     * @return Collection<int, Spoke>
     */
    public function all(): Collection
    {
        return $this->catalogue ??= Spoke::active()->ordered()->get();
    }

    /**
     * Spokes that the given tenant has access to (active subscription).
     *
     * @return Collection<int, Spoke>
     */
    public function forTenant(Tenant $tenant): Collection
    {
        return $this->all()
            ->filter(fn (Spoke $spoke) => $tenant->hasSpokeAccess($spoke))
            ->values();
    }

    /**
     * Resolve a Spoke by slug.
     */
    public function findBySlug(string $slug): ?Spoke
    {
        return $this->all()->first(fn (Spoke $spoke) => $spoke->slug === $slug);
    }

    /**
     * Determine whether the given tenant has access to the Spoke identified by slug.
     */
    public function tenantHasAccess(Tenant $tenant, string $spokeSlug): bool
    {
        $spoke = $this->findBySlug($spokeSlug);

        return $spoke !== null && $tenant->hasSpokeAccess($spoke);
    }

    /**
     * Flush the in-memory catalogue (useful for testing or CLI commands that
     * create/update Spokes during the same request).
     */
    public function flush(): void
    {
        $this->catalogue = null;
    }
}
