<?php

namespace App\Models;

use App\Shared\Domain\ValueObject\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property int $duration_minutes
 * @property Collection<int, ServicePrice> $prices
 */
#[Fillable(['category_id', 'name', 'duration_minutes', 'is_active'])]
class Service extends Model
{
    use HasUuids;

    /** @return BelongsTo<ServiceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    /** @return HasMany<ServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class)->orderByDesc('effective_from');
    }

    /** La tarifa vigente ahora; la cita congela la suya al solicitarse. */
    public function currentPrice(): ?Money
    {
        $price = $this->prices->first(fn (ServicePrice $price): bool => $price->effective_from <= now());

        return $price ? new Money($price->amount_cents, $price->currency) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
