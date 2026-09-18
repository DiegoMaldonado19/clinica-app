<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use DateTimeImmutable;

final readonly class AppointmentCheckedIn extends AppointmentEvent
{
    public function __construct(
        string $appointmentId,
        string $patientId,
        DateTimeImmutable $occurredAt,
        public string $checkedInBy,
        public bool $paidAtDesk,
    ) {
        parent::__construct($appointmentId, $patientId, $occurredAt);
    }
}
