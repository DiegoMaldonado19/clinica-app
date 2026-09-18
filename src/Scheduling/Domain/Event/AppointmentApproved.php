<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use DateTimeImmutable;

final readonly class AppointmentApproved extends AppointmentEvent
{
    public function __construct(
        string $appointmentId,
        string $patientId,
        DateTimeImmutable $occurredAt,
        public int $feeAmountCents,
        public string $feeCurrency,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $paymentDueAt,
    ) {
        parent::__construct($appointmentId, $patientId, $occurredAt);
    }
}
