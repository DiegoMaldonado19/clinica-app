<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $amount_cents
 * @property string $currency
 * @property Carbon $effective_from
 */
#[Fillable(['amount_cents', 'currency', 'effective_from'])]
class ServicePrice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_from' => 'datetime'];
    }
}
