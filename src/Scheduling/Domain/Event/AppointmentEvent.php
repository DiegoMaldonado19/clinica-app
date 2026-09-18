<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

abstract readonly class AppointmentEvent implements DomainEvent
{
    public function __construct(
        public string $appointmentId,
        public string $patientId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->appointmentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
