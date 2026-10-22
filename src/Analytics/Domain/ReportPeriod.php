<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Rango del reporte: desde la medianoche local de `from` hasta la de `to`, sin incluirla. */
final readonly class ReportPeriod
{
    public const PRESETS = [
        'month' => 'Este mes',
        'previous' => 'Mes anterior',
        '90d' => 'Últimos 90 días',
        'year' => 'Este año',
    ];

    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
        if ($to <= $from) {
            throw new InvalidArgumentException('El periodo debe terminar después de empezar.');
        }
    }

    /** Un valor desconocido cae en «Este mes»: el filtro llega del navegador. */
    public static function preset(string $key, DateTimeImmutable $now, DateTimeZone $timezone): self
    {
        $today = $now->setTimezone($timezone)->setTime(0, 0);

        return match ($key) {
            'previous' => new self($today->modify('first day of last month'), $today->modify('first day of this month')),
            '90d' => new self($today->modify('-89 days'), $today->modify('+1 day')),
            'year' => new self($today->modify('first day of january this year'), $today->modify('first day of january next year')),
            default => new self($today->modify('first day of this month'), $today->modify('first day of next month')),
        };
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->to;
    }
}
