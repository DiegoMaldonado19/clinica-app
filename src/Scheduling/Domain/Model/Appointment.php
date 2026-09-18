<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Model;

use App\Scheduling\Domain\Event\AppointmentApproved;
use App\Scheduling\Domain\Event\AppointmentCancelled;
use App\Scheduling\Domain\Event\AppointmentCancelledUnpaid;
use App\Scheduling\Domain\Event\AppointmentCheckedIn;
use App\Scheduling\Domain\Event\AppointmentFullyBooked;
use App\Scheduling\Domain\Event\AppointmentNoShow;
use App\Scheduling\Domain\Event\AppointmentRejected;
use App\Scheduling\Domain\Event\PayAtDeskChosen;
use App\Scheduling\Domain\Event\PreAppointmentExpired;
use App\Scheduling\Domain\Event\PreAppointmentRequested;
use App\Scheduling\Domain\Exception\InvalidStateTransition;
use App\Scheduling\Domain\Policy\ApprovalSlaPolicy;
use App\Scheduling\Domain\Policy\BookingWindowPolicy;
use App\Scheduling\Domain\Policy\CancellationOutcome;
use App\Scheduling\Domain\Policy\TieredCancellationPolicy;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use DomainException;

/**
 * Unico agregado que cambia el estado de una cita (doc 08 §1). Cada cambio
 * valida la transicion contra `AppointmentStatus` y deja el evento listo para
 * publicar; nadie mas escribe `status_id`.
 */
final class Appointment
{
    /** @var list<DomainEvent> */
    private array $events = [];

    /** @var list<array{from: ?AppointmentStatus, to: AppointmentStatus, by: ?string, reason: ?string, at: DateTimeImmutable}> */
    private array $transitions = [];

    private function __construct(
        public readonly string $id,
        public readonly string $patientId,
        public readonly string $therapistId,
        public readonly string $serviceId,
        public readonly TimeSlot $slot,
        public readonly Money $fee,
        public readonly BookingSource $source,
        private AppointmentStatus $status,
        private ?DateTimeImmutable $holdExpiresAt = null,
        private ?DateTimeImmutable $paymentDueAt = null,
        private ?string $approvedBy = null,
        private ?string $rejectionReason = null,
    ) {}

    /** to-be-01: solicitud en linea, sujeta a RN-01/RN-02 y con HOLD durante el SLA. */
    public static function request(
        string $id,
        string $patientId,
        string $therapistId,
        string $serviceId,
        TimeSlot $slot,
        Money $fee,
        BookingSource $source,
        BookingWindowPolicy $window,
        ApprovalSlaPolicy $sla,
        ClockInterface $clock,
    ): self {
        $now = $clock->now();
        $window->assertBookable($slot, $now);

        $appointment = new self($id, $patientId, $therapistId, $serviceId, $slot, $fee, $source, AppointmentStatus::SOLICITADA);
        $appointment->holdExpiresAt = $sla->deadlineFor($now, $slot);
        $appointment->record(null, AppointmentStatus::SOLICITADA, null, null, $now);
        $appointment->events[] = new PreAppointmentRequested($id, $patientId, $now, $appointment->holdExpiresAt);

        return $appointment;
    }

    /**
     * to-be-09: recepcion agenda por telefono o en persona. Excepcion a RN-01,
     * nace ya aprobada; si el plazo de pago ya paso, se cobra en caja.
     */
    public static function bookAssisted(
        string $id,
        string $patientId,
        string $therapistId,
        string $serviceId,
        TimeSlot $slot,
        Money $fee,
        BookingSource $source,
        string $bookedBy,
        int $paymentDeadlineHours,
        ClockInterface $clock,
    ): self {
        $appointment = new self($id, $patientId, $therapistId, $serviceId, $slot, $fee, $source, AppointmentStatus::SOLICITADA);
        $appointment->record(null, AppointmentStatus::SOLICITADA, $bookedBy, null, $clock->now());
        $appointment->approve($bookedBy, $paymentDeadlineHours, $clock);

        if ($appointment->paymentDueAt <= $clock->now()) {
            $appointment->payAtDesk($clock);
        }

        return $appointment;
    }

    /**
     * Reconstruye el agregado desde persistencia, sin eventos ni validaciones.
     */
    public static function restore(
        string $id,
        string $patientId,
        string $therapistId,
        string $serviceId,
        TimeSlot $slot,
        Money $fee,
        BookingSource $source,
        AppointmentStatus $status,
        ?DateTimeImmutable $holdExpiresAt,
        ?DateTimeImmutable $paymentDueAt,
        ?string $approvedBy,
        ?string $rejectionReason,
    ): self {
        return new self(
            $id, $patientId, $therapistId, $serviceId, $slot, $fee, $source,
            $status, $holdExpiresAt, $paymentDueAt, $approvedBy, $rejectionReason,
        );
    }

    public function approve(string $approvedBy, int $paymentDeadlineHours, ClockInterface $clock): void
    {
        $this->transition(AppointmentStatus::CONFIRMADA_PENDIENTE_PAGO, $approvedBy, null, $clock->now());
        $this->approvedBy = $approvedBy;
        $this->holdExpiresAt = null;
        $this->paymentDueAt = $this->slot->startsAt->modify("-{$paymentDeadlineHours} hours");

        $this->events[] = new AppointmentApproved(
            $this->id, $this->patientId, $clock->now(),
            $this->fee->amountInCents, $this->fee->currency, $this->slot->startsAt, $this->paymentDueAt,
        );
    }

    public function reject(string $rejectedBy, string $reason, ClockInterface $clock): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Rechazar una solicitud exige un motivo.');
        }

        $this->transition(AppointmentStatus::RECHAZADA, $rejectedBy, $reason, $clock->now());
        $this->rejectionReason = $reason;
        $this->events[] = new AppointmentRejected($this->id, $this->patientId, $clock->now(), $reason);
    }

    public function holdHasExpired(DateTimeImmutable $now): bool
    {
        return $this->status === AppointmentStatus::SOLICITADA
            && $this->holdExpiresAt !== null
            && $this->holdExpiresAt <= $now;
    }

    public function expire(ClockInterface $clock): void
    {
        if (! $this->holdHasExpired($clock->now())) {
            throw new DomainException('La solicitud todavia esta dentro de su plazo de aprobacion.');
        }

        $this->transition(AppointmentStatus::EXPIRADA, null, 'SLA_VENCIDO', $clock->now());
        $this->events[] = new PreAppointmentExpired($this->id, $this->patientId, $clock->now());
    }

    public function submitPayment(ClockInterface $clock): void
    {
        $this->transition(AppointmentStatus::PAGO_EN_REVISION, null, null, $clock->now());
    }

    public function rejectPayment(ClockInterface $clock): void
    {
        $this->transition(AppointmentStatus::CONFIRMADA_PENDIENTE_PAGO, null, null, $clock->now());
    }

    public function payAtDesk(ClockInterface $clock): void
    {
        $this->transition(AppointmentStatus::PAGO_EN_CAJA, null, null, $clock->now());
        $this->events[] = new PayAtDeskChosen($this->id, $this->patientId, $clock->now());
    }

    /** RN-07: solo el pago aprobado o cobrado lleva la cita a AGENDADA. */
    public function confirmPayment(ClockInterface $clock): void
    {
        $this->transition(AppointmentStatus::AGENDADA, null, null, $clock->now());
        $this->events[] = new AppointmentFullyBooked($this->id, $this->patientId, $clock->now());
    }

    /** RN-05: un comprobante en revision no detiene el reloj; el pago en caja si. */
    public function paymentIsOverdue(DateTimeImmutable $now): bool
    {
        return in_array($this->status, [AppointmentStatus::CONFIRMADA_PENDIENTE_PAGO, AppointmentStatus::PAGO_EN_REVISION], true)
            && $this->paymentDueAt !== null
            && $this->paymentDueAt <= $now;
    }

    public function cancelUnpaid(ClockInterface $clock): void
    {
        if (! $this->paymentIsOverdue($clock->now())) {
            throw new DomainException('La cita no esta vencida por impago.');
        }

        $this->transition(AppointmentStatus::CANCELADA_IMPAGO, null, 'IMPAGO', $clock->now());
        $this->events[] = new AppointmentCancelledUnpaid($this->id, $this->patientId, $clock->now());
    }

    public function cancel(TieredCancellationPolicy $policy, ClockInterface $clock, ?string $cancelledBy = null): CancellationOutcome
    {
        if (! $this->status->isCancellable()) {
            throw new DomainException('Esta cita no se puede cancelar en su estado actual.');
        }

        $outcome = $policy->evaluate($this->slot, $this->fee, $clock->now());
        $to = $outcome->feeApplies ? AppointmentStatus::CANCELADA_CON_RECARGO : AppointmentStatus::CANCELADA_SIN_CARGO;

        $this->transition($to, $cancelledBy, 'PACIENTE_SOLICITO', $clock->now());
        $this->events[] = new AppointmentCancelled(
            $this->id, $this->patientId, $clock->now(), $outcome->feeAmount->amountInCents, $outcome->feePercentage, false,
        );

        return $outcome;
    }

    /** RN-18: la indisponibilidad de la terapeuta nunca le cuesta al paciente. */
    public function cancelByTherapist(string $cancelledBy, ClockInterface $clock): void
    {
        if ($this->status === AppointmentStatus::SOLICITADA) {
            $this->reject($cancelledBy, 'La psicóloga no estará disponible en ese horario.', $clock);

            return;
        }

        if (! $this->status->isCancellable()) {
            throw new DomainException('Esta cita no se puede cancelar en su estado actual.');
        }

        $this->transition(AppointmentStatus::CANCELADA_SIN_CARGO, $cancelledBy, 'TERAPEUTA_NO_DISPONIBLE', $clock->now());
        $this->events[] = new AppointmentCancelled($this->id, $this->patientId, $clock->now(), 0, 0, true);
    }

    /** Una cita en caja se cobra al llegar: pasa por AGENDADA antes de EN_CURSO. */
    public function checkIn(string $checkedInBy, ClockInterface $clock): void
    {
        $paidAtDesk = $this->status === AppointmentStatus::PAGO_EN_CAJA;

        if ($paidAtDesk) {
            $this->confirmPayment($clock);
        }

        $this->transition(AppointmentStatus::EN_CURSO, $checkedInBy, null, $clock->now());
        $this->events[] = new AppointmentCheckedIn($this->id, $this->patientId, $clock->now(), $checkedInBy, $paidAtDesk);
    }

    /** RF-30: 100 % de cargo y el horario no se libera. */
    public function markNoShow(string $markedBy, ClockInterface $clock): CancellationOutcome
    {
        if ($this->slot->startsAt > $clock->now()) {
            throw new DomainException('No se puede marcar inasistencia antes de la hora de la cita.');
        }

        $this->transition(AppointmentStatus::NO_SHOW, $markedBy, 'NO_SHOW', $clock->now());
        $this->events[] = new AppointmentNoShow($this->id, $this->patientId, $clock->now());

        return new CancellationOutcome(true, $this->fee, 100, 'no_show');
    }

    public function complete(ClockInterface $clock): void
    {
        $this->transition(AppointmentStatus::ATENDIDA, null, null, $clock->now());
    }

    public function status(): AppointmentStatus
    {
        return $this->status;
    }

    public function holdExpiresAt(): ?DateTimeImmutable
    {
        return $this->holdExpiresAt;
    }

    public function paymentDueAt(): ?DateTimeImmutable
    {
        return $this->paymentDueAt;
    }

    public function approvedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }

    /** @return list<array{from: ?AppointmentStatus, to: AppointmentStatus, by: ?string, reason: ?string, at: DateTimeImmutable}> */
    public function releaseTransitions(): array
    {
        [$transitions, $this->transitions] = [$this->transitions, []];

        return $transitions;
    }

    private function transition(AppointmentStatus $to, ?string $by, ?string $reason, DateTimeImmutable $at): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new InvalidStateTransition($this->status, $to);
        }

        $this->record($this->status, $to, $by, $reason, $at);
        $this->status = $to;
    }

    private function record(?AppointmentStatus $from, AppointmentStatus $to, ?string $by, ?string $reason, DateTimeImmutable $at): void
    {
        $this->transitions[] = ['from' => $from, 'to' => $to, 'by' => $by, 'reason' => $reason, 'at' => $at];
    }
}
