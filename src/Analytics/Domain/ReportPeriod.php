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

    /** Un rango elegido llega hasta un año, como «Este año»: el reporte recorre el periodo día por día. */
    public const LONGEST_RANGE = '1 year';

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

    /**
     * Del primer al último día elegidos, ambos incluidos y en hora local. Las fechas llegan
     * del navegador, con o sin hora: nulo si no forman un rango válido.
     */
    public static function tryDays(string $first, string $last, DateTimeZone $timezone): ?self
    {
        $from = self::day($first, $timezone);
        $to = self::day($last, $timezone)?->modify('+1 day');

        return $from !== null && $to !== null && $from < $to && $to <= $from->modify('+'.self::LONGEST_RANGE)
            ? new self($from, $to)
            : null;
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->to;
    }

    private static function day(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $date = substr($value, 0, 10);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        return $day !== false && $day->format('Y-m-d') === $date ? $day : null;
    }
}
