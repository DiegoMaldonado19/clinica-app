<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence;

use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\Model\Appointment;
use App\Scheduling\Domain\Model\AppointmentStatus;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\Catalog;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Query builder y no Eloquent: el mapeo fila <-> agregado queda explicito y el
 * dominio no hereda ninguna magia del ORM.
 */
final readonly class DatabaseAppointmentRepository implements AppointmentRepository
{
    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Catalog $catalog) {}

    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * FOR UPDATE: dentro de la transaccion de cada comando, dos transiciones
     * sobre la misma cita (aprobar y expirar al mismo segundo) se serializan.
     */
    public function find(string $id): ?Appointment
    {
        $row = DB::table('appointments')->where('id', $id)->lockForUpdate()->first();

        if ($row === null) {
            return null;
        }

        return Appointment::restore(
            $row->id, $row->patient_id, $row->therapist_id, $row->service_id,
            new TimeSlot($this->date($row->starts_at), $this->date($row->ends_at)),
            new Money((int) $row->fee_amount_cents, $row->fee_currency),
            BookingSource::from($row->source),
            AppointmentStatus::from($this->catalog->code('appointment_statuses', (int) $row->status_id)),
            $row->hold_expires_at ? $this->date($row->hold_expires_at) : null,
            $row->payment_due_at ? $this->date($row->payment_due_at) : null,
            $row->approved_by,
            $row->rejection_reason,
        );
    }

    public function save(Appointment $appointment): void
    {
        $transitions = $appointment->releaseTransitions();
        $now = now();

        $row = [
            'status_id' => $this->statusId($appointment->status()),
            'hold_expires_at' => $this->format($appointment->holdExpiresAt()),
            'payment_due_at' => $this->format($appointment->paymentDueAt()),
            'approved_by' => $appointment->approvedBy(),
            'rejection_reason' => $appointment->rejectionReason(),
            'active_slot' => $appointment->status()->releasesSlot() ? null : 1,
            'updated_at' => $now,
        ] + $this->timestampsFor($transitions);

        DB::transaction(function () use ($appointment, $row, $transitions, $now) {
            // Insert y update explicitos, nunca upsert: ON DUPLICATE KEY tambien
            // salta con el indice del horario y pisaria la cita de otra persona.
            if (DB::table('appointments')->where('id', $appointment->id)->exists()) {
                DB::table('appointments')->where('id', $appointment->id)->update($row);
            } else {
                $this->insert($appointment, $row, $transitions[0]['at'] ?? null, $now);
            }

            DB::table('appointment_status_log')->insert(array_map(fn (array $t): array => [
                'appointment_id' => $appointment->id,
                'from_status_id' => $t['from'] ? $this->statusId($t['from']) : null,
                'to_status_id' => $this->statusId($t['to']),
                'changed_by' => $t['by'],
                'reason_text' => $t['reason'],
                'occurred_at' => $t['at']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
            ], $transitions));
        });

        DatabaseAvailabilityProvider::forget(
            $appointment->therapistId, $appointment->slot->startsAt, new DateTimeZone(config('clinic.timezone')),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insert(Appointment $appointment, array $row, ?DateTimeImmutable $requestedAt, Carbon $now): void
    {
        try {
            DB::table('appointments')->insert([
                'id' => $appointment->id,
                'patient_id' => $appointment->patientId,
                'therapist_id' => $appointment->therapistId,
                'service_id' => $appointment->serviceId,
                'starts_at' => $this->format($appointment->slot->startsAt),
                'ends_at' => $this->format($appointment->slot->endsAt),
                'fee_amount_cents' => $appointment->fee->amountInCents,
                'fee_currency' => $appointment->fee->currency,
                'requested_at' => $this->format($requestedAt) ?? $now,
                'source' => $appointment->source->value,
                'created_at' => $now,
            ] + $row);
        } catch (UniqueConstraintViolationException) {
            throw new SlotUnavailable;
        }
    }

    public function idsWithExpiredHolds(DateTimeImmutable $now): array
    {
        return $this->idsWhere(AppointmentStatus::SOLICITADA, 'hold_expires_at', $now);
    }

    public function idsWithOverduePayments(DateTimeImmutable $now): array
    {
        return array_merge(
            $this->idsWhere(AppointmentStatus::CONFIRMADA_PENDIENTE_PAGO, 'payment_due_at', $now),
            $this->idsWhere(AppointmentStatus::PAGO_EN_REVISION, 'payment_due_at', $now),
        );
    }

    /** @return list<string> */
    private function idsWhere(AppointmentStatus $status, string $column, DateTimeImmutable $now): array
    {
        return DB::table('appointments')
            ->where('status_id', $this->statusId($status))
            ->where($column, '<=', $this->format($now))
            ->pluck('id')
            ->all();
    }

    /**
     * Las columnas de sello de tiempo que el doc 06 pide junto al estado.
     *
     * @param  list<array{from: ?AppointmentStatus, to: AppointmentStatus, by: ?string, reason: ?string, at: DateTimeImmutable}>  $transitions
     * @return array<string, string>
     */
    private function timestampsFor(array $transitions): array
    {
        $columns = [];

        foreach ($transitions as $t) {
            $column = match ($t['to']) {
                AppointmentStatus::CONFIRMADA_PENDIENTE_PAGO => $t['from'] === AppointmentStatus::SOLICITADA ? 'approved_at' : null,
                AppointmentStatus::EN_CURSO => 'checked_in_at',
                AppointmentStatus::ATENDIDA => 'completed_at',
                default => null,
            };

            if ($column !== null) {
                $columns[$column] = (string) $this->format($t['at']);
            }
        }

        return $columns;
    }

    private function statusId(AppointmentStatus $status): int
    {
        return $this->catalog->id('appointment_statuses', $status->value);
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function format(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
