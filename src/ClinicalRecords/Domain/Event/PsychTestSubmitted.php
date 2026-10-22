<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

/** Sin respuestas ni puntuacion: solo si pide revision urgente (NT-25). */
final readonly class PsychTestSubmitted implements DomainEvent
{
    public function __construct(
        public string $assignmentId,
        public string $patientId,
        public bool $urgent,
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
