<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

/**
 * Lleva la credencial temporal hasta NT-02. No se persiste en claro: el motor
 * de notificaciones cifra su carga antes de guardarla.
 */
final readonly class PatientAccountCreated implements DomainEvent
{
    public function __construct(
        public string $patientId,
        public DateTimeImmutable $occurredAt,
        #[\SensitiveParameter] public string $temporaryPassword,
        public int $passwordTtlHours,
    ) {}

    public function aggregateId(): string
    {
        return $this->patientId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
