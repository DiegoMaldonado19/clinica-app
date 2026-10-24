<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

/** NT-17: la fecha limite paso sin respuesta. */
final readonly class PsychTestOverdue implements DomainEvent
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
