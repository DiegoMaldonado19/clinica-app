<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use DateTimeImmutable;

/**
 * Contrato explicito entre contextos: cada evento declara sus campos como
 * propiedades tipadas. Nunca se publica un modelo convertido a arreglo.
 */
interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredAt(): DateTimeImmutable;
}
