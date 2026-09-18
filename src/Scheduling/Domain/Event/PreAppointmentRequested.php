<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use DateTimeImmutable;

final readonly class PreAppointmentRequested extends AppointmentEvent
{
    public function __construct(
        string $appointmentId,
        string $patientId,
        DateTimeImmutable $occurredAt,
        public DateTimeImmutable $holdExpiresAt,
    ) {
        parent::__construct($appointmentId, $patientId, $occurredAt);
    }
}
