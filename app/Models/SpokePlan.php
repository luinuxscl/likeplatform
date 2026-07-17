<?php

namespace App\Models;

use Database\Factories\SpokePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $spoke_id
 * @property string $name
 * @property string $slug
 * @property int|null $price_cents
 * @property array<int, string>|null $features
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Spoke $spoke
 */
#[Fillable(['spoke_id', 'name', 'slug', 'price_cents', 'features', 'sort_order'])]
class SpokePlan extends Model
{
    /** @use HasFactory<SpokePlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'features' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Spoke, $this> */
    public function spoke(): BelongsTo
    {
        return $this->belongsTo(Spoke::class);
    }
}
