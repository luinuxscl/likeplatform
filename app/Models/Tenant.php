<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property bool $is_suspended
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Document> $documents
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Spoke> $activeSpokes
 */
#[Fillable(['name', 'slug'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The users that belong to this tenant.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('joined_at', 'invited_by')
            ->withTimestamps();
    }

    /**
     * The documents that belong to this tenant.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The invoices that belong to this tenant.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The Spokes this tenant has an active subscription for.
     *
     * @return BelongsToMany<Spoke, $this>
     */
    public function activeSpokes(): BelongsToMany
    {
        return $this->belongsToMany(Spoke::class, 'tenant_spoke_subscriptions')
            ->withPivot(['id', 'spoke_plan_id', 'is_active', 'subscribed_at', 'expires_at'])
            ->wherePivot('is_active', true)
            ->withTimestamps();
    }

    /**
     * All Spoke subscriptions (including inactive/expired), useful for admin views.
     *
     * @return BelongsToMany<Spoke, $this>
     */
    public function spokeSubscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Spoke::class, 'tenant_spoke_subscriptions')
            ->withPivot(['id', 'spoke_plan_id', 'is_active', 'subscribed_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Determine whether this tenant has an active subscription to the given Spoke.
     */
    public function hasSpokeAccess(Spoke $spoke): bool
    {
        return $this->activeSpokes()->where('spokes.id', $spoke->id)->exists();
    }

    /**
     * Grant or reactivate a Spoke subscription for this tenant.
     */
    public function grantSpoke(Spoke|int $spoke, ?SpokePlan $plan = null): void
    {
        $spokeId = $spoke instanceof Spoke ? $spoke->id : $spoke;

        $this->spokeSubscriptions()->syncWithoutDetaching([
            $spokeId => [
                'spoke_plan_id' => $plan?->id,
                'is_active' => true,
                'subscribed_at' => now(),
            ],
        ]);
    }

    /**
     * Revoke a Spoke subscription (set it to inactive without deleting).
     */
    public function revokeSpoke(Spoke|int $spoke): void
    {
        $spokeId = $spoke instanceof Spoke ? $spoke->id : $spoke;

        $this->spokeSubscriptions()->updateExistingPivot($spokeId, [
            'is_active' => false,
        ]);
    }

    /**
     * Scope a query to only include active (not suspended) tenants.
     *
     * @param  Builder<Tenant>  $query
     * @return Builder<Tenant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_suspended', false);
    }

    /**
     * Determine if the tenant is operational.
     */
    public function isOperational(): bool
    {
        return $this->is_active && ! $this->is_suspended;
    }
}
