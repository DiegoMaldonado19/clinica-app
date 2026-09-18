<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use DateTimeImmutable;

final readonly class AppointmentCancelled extends AppointmentEvent
{
    public function __construct(
        string $appointmentId,
        string $patientId,
        DateTimeImmutable $occurredAt,
        public int $feeAmountCents,
        public int $feePercentage,
        public bool $byTherapist,
    ) {
        parent::__construct($appointmentId, $patientId, $occurredAt);
    }
}
