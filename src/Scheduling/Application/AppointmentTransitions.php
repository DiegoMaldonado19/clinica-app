<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Scheduling\Domain\Model\Appointment;
use App\Scheduling\Domain\Policy\CancellationOutcome;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use DomainException;

/**
 * Un metodo por comando del ciclo de vida de la cita. Cada uno carga, delega en
 * el agregado, guarda y publica; ninguno decide una regla.
 */
final readonly class AppointmentTransitions
{
    public function __construct(
        private AppointmentRepository $appointments,
        private SchedulingPolicies $policies,
        private EventBus $events,
        private ClockInterface $clock,
    ) {}

    public function approve(string $id, string $approvedBy): void
    {
        $this->apply($id, fn (Appointment $a) => $a->approve($approvedBy, $this->policies->paymentDeadlineHours(), $this->clock));
    }

    public function reject(string $id, string $rejectedBy, string $reason): void
    {
        $this->apply($id, fn (Appointment $a) => $a->reject($rejectedBy, $reason, $this->clock));
    }

    /** Idempotente (doc 02 §8.1): sin efecto si ya se resolvio o aun no vence. */
    public function expireIfDue(string $id): bool
    {
        $appointment = $this->appointments->find($id);

        if ($appointment === null || ! $appointment->holdHasExpired($this->clock->now())) {
            return false;
        }

        $this->apply($id, fn (Appointment $a) => $a->expire($this->clock), $appointment);

        return true;
    }

    /** Idempotente: una cita pagada, en caja o ya cancelada no se toca. */
    public function cancelIfUnpaid(string $id): bool
    {
        $appointment = $this->appointments->find($id);

        if ($appointment === null || ! $appointment->paymentIsOverdue($this->clock->now())) {
            return false;
        }

        $this->apply($id, fn (Appointment $a) => $a->cancelUnpaid($this->clock), $appointment);

        return true;
    }

    /** RF-24: el cargo exacto antes de confirmar. */
    public function previewCancellation(string $id): CancellationOutcome
    {
        $appointment = $this->load($id);

        return $this->policies->cancellation()->evaluate($appointment->slot, $appointment->fee, $this->clock->now());
    }

    public function cancel(string $id, ?string $cancelledBy): CancellationOutcome
    {
        return $this->apply($id, fn (Appointment $a) => $a->cancel($this->policies->cancellation(), $this->clock, $cancelledBy));
    }

    public function cancelByTherapist(string $id, string $cancelledBy): void
    {
        $this->apply($id, fn (Appointment $a) => $a->cancelByTherapist($cancelledBy, $this->clock));
    }

    public function payAtDesk(string $id): void
    {
        $this->apply($id, fn (Appointment $a) => $a->payAtDesk($this->clock));
    }

    public function checkIn(string $id, string $checkedInBy): void
    {
        $this->apply($id, fn (Appointment $a) => $a->checkIn($checkedInBy, $this->clock));
    }

    public function markNoShow(string $id, string $markedBy): CancellationOutcome
    {
        return $this->apply($id, fn (Appointment $a) => $a->markNoShow($markedBy, $this->clock));
    }

    public function submitPayment(string $id): void
    {
        $this->apply($id, fn (Appointment $a) => $a->submitPayment($this->clock));
    }

    public function confirmPayment(string $id): void
    {
        $this->apply($id, fn (Appointment $a) => $a->confirmPayment($this->clock));
    }

    public function rejectPayment(string $id): void
    {
        $this->apply($id, fn (Appointment $a) => $a->rejectPayment($this->clock));
    }

    public function complete(string $id): void
    {
        $this->apply($id, fn (Appointment $a) => $a->complete($this->clock));
    }

    /**
     * @template T
     *
     * @param  callable(Appointment): T  $change
     * @return T
     */
    private function apply(string $id, callable $change, ?Appointment $appointment = null): mixed
    {
        $appointment ??= $this->load($id);
        $result = $change($appointment);

        $this->appointments->save($appointment);
        $this->events->publish(...$appointment->releaseEvents());

        return $result;
    }

    private function load(string $id): Appointment
    {
        return $this->appointments->find($id) ?? throw new DomainException('La cita no existe.');
    }
}
