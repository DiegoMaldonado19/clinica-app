<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

/** Sin contenido clinico: solo que existe una version sellada. */
final readonly class ClinicalNoteSealed implements DomainEvent
{
    public function __construct(
        public string $noteId,
        public string $patientId,
        public ?string $appointmentId,
        public int $version,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->noteId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
