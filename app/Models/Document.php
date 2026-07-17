<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use League\CommonMark\CommonMarkConverter;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 */
#[Fillable(['title', 'slug', 'content', 'tenant_id', 'user_id'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Render the markdown content as HTML.
     */
    public function toHtml(): string
    {
        if (blank($this->content)) {
            return '';
        }

        return (new CommonMarkConverter(['html_input' => 'escape']))->convert($this->content)->getContent();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Scope a query to only include documents for a given tenant.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->where('tenant_id', $tenantId);
    }
}
