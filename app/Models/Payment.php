<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Lectura para la conciliacion (D-03). Los cambios pasan por BillingService.
 *
 * @property string $id
 * @property string $appointment_id
 * @property string $kind
 * @property int $amount_cents
 */
class Payment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<PaymentStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class);
    }

    /**
     * El ultimo comprobante: tras un rechazo el paciente puede subir otro.
     *
     * @return HasOne<PaymentProof, $this>
     */
    public function latestProof(): HasOne
    {
        return $this->hasOne(PaymentProof::class)->latestOfMany('uploaded_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'waived_at' => 'datetime',
        ];
    }
}
