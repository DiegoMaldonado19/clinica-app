<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Port;

use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

interface ServiceCatalog
{
    /**
     * Duracion y tarifa vigentes. La cita congela esta tarifa al crearse.
     *
     * @return array{durationMinutes: int, fee: Money}|null null si el servicio no existe o esta inactivo
     */
    public function quote(string $serviceId, DateTimeImmutable $at): ?array;
}
