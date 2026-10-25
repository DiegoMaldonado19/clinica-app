<?php

namespace App\Models;

use App\Scheduling\Domain\Model\AppointmentStatus as DomainStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lectura para los paneles. El estado solo lo cambia el agregado del dominio a
 * traves de su repositorio; este modelo no tiene `fillable`.
 *
 * @property string $id
 * @property string $patient_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $payment_due_at
 * @property string $service_id
 * @property int $fee_amount_cents
 * @property string $source
 */
class Appointment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @return BelongsTo<User, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<AppointmentStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<AppointmentStatusLog, $this> */
    public function statusLog(): HasMany
    {
        return $this->hasMany(AppointmentStatusLog::class)->with(['toStatus:id,label', 'author:id,name'])->orderBy('occurred_at');
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<string>  $codes
     */
    public function scopeInStatus(Builder $query, array $codes): void
    {
        $query->whereIn('status_id', AppointmentStatus::query()->whereIn('code', $codes)->select('id'));
    }

    public function statusCode(): string
    {
        return $this->status->code;
    }

    /** Lo decide el enum del dominio: lo que se puede cancelar tambien se puede reprogramar. */
    public function isCancellable(): bool
    {
        return DomainStatus::from($this->statusCode())->isCancellable();
    }

    /** A pedido del paciente, solo antes del inicio (ADR-039). Por indisponibilidad basta `isCancellable()`. */
    public function canBeCancelledByPatient(): bool
    {
        return $this->isCancellable() && $this->starts_at->isFuture();
    }

    /** El cobro de la sesion; despues del credito a favor puede ser menor que la tarifa (RN-16). */
    public function sessionPayment(): ?Payment
    {
        return $this->payments->firstWhere('kind', 'SESSION_FEE');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'requested_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'approved_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }
}
