<?php

namespace App\Models;

use Database\Factories\SpokeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SpokePlan> $plans
 */
#[Fillable(['name', 'slug', 'description', 'icon', 'is_active', 'sort_order'])]
class Spoke extends Model
{
    /** @use HasFactory<SpokeFactory> */
    use HasFactory;

    /**
     * Known valid Flux icon names. Used to guard against invalid
     * icon references that would cause Blade rendering to fail.
     */
    public const VALID_ICONS = [
        'document-text', 'banknotes', 'folder', 'chart-bar', 'users',
        'squares-2x2', 'home', 'cog', 'inbox', 'currency-dollar',
        'clipboard-document-list', 'receipt-percent', 'academic-cap',
        'beaker', 'briefcase', 'building-office', 'calendar-days',
        'clock', 'envelope', 'globe-alt', 'key', 'shield-check',
        'star', 'truck', 'wrench',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Return a safe Flux icon name, falling back to a default if the
     * stored icon is not recognized by the system.
     */
    public function safeIcon(): string
    {
        return in_array($this->icon, self::VALID_ICONS, true) ? $this->icon : 'squares-2x2';
    }

    /** @return HasMany<SpokePlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(SpokePlan::class)->orderBy('sort_order');
    }

    /** @param  Builder<Spoke>  $query
     *  @return Builder<Spoke> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param  Builder<Spoke>  $query
     *  @return Builder<Spoke> */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Resolve the full route prefix for this spoke (e.g. '/app/docs').
     */
    public function path(): string
    {
        return '/app/'.$this->slug;
    }

    /**
     * Resolve the named-route prefix for this spoke (e.g. 'docs.').
     */
    public function routePrefix(): string
    {
        return $this->slug.'.';
    }
}
