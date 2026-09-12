<?php

declare(strict_types=1);

namespace App\Shared\Domain\Clock;

use DateTimeImmutable;

/**
 * Todo el dominio depende de plazos: 24 h, 72 h, 3 h, 1 h, T-24 h, T-2 h.
 * Inyectar el reloj es lo que permite probarlos sin esperar horas reales.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
