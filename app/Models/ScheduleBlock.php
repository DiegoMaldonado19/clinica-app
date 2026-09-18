<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $therapist_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['therapist_id', 'starts_at', 'ends_at', 'reason', 'created_by'])]
class ScheduleBlock extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
