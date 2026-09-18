<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Job;

use App\Scheduling\Domain\Event\ReminderDue;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** RN-08. Una cita cancelada o terminada no recibe su recordatorio. */
final class SendReminderJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $appointmentId,
        public readonly int $hoursBefore,
    ) {}

    public function handle(AppointmentRepository $appointments, EventBus $events, ClockInterface $clock): void
    {
        $appointment = $appointments->find($this->appointmentId);

        if ($appointment === null || $appointment->status()->isTerminal()) {
            return;
        }

        // occurredAt fijo al inicio de la cita: el mismo recordatorio reprocesado
        // produce la misma clave de idempotencia.
        $events->publish(new ReminderDue($appointment->id, $appointment->patientId, $appointment->slot->startsAt, $this->hoursBefore));
    }
}
