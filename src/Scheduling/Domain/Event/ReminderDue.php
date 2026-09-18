<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use DateTimeImmutable;

final readonly class ReminderDue extends AppointmentEvent
{
    public function __construct(
        string $appointmentId,
        string $patientId,
        DateTimeImmutable $occurredAt,
        public int $hoursBefore,
    ) {
        parent::__construct($appointmentId, $patientId, $occurredAt);
    }
}
