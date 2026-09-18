<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentStatusLog extends Model
{
    protected $table = 'appointment_status_log';

    public $timestamps = false;

    /** @return BelongsTo<AppointmentStatus, $this> */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class, 'to_status_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
