<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock\ClockInterface;
use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    /** Pasa por Carbon para que `travelTo()` de las pruebas mueva tambien al dominio. */
    public function now(): DateTimeImmutable
    {
        return now('UTC')->toDateTimeImmutable();
    }
}
