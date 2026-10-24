<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

/** NT-16. Sin el instrumento: el aviso no dice que se evalua. */
final readonly class PsychTestAssigned implements DomainEvent
{
    public function __construct(
        public string $assignmentId,
        public string $patientId,
        public DateTimeImmutable $dueAt,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->assignmentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
