<?php

declare(strict_types=1);

namespace App\Scheduling\Domain;

use App\Scheduling\Domain\ValueObject\TimeSlot;
use DateTimeImmutable;

/**
 * Horario publicado menos lo ocupado. Los horarios arrancan en punto: con
 * servicios de 45 a 60 minutos, dos citas solo chocan si empiezan a la vez.
 */
final class AvailabilityCalculator
{
    private const STEP_MINUTES = 60;

    /**
     * @param  DateTimeImmutable  $day  medianoche local del dia a calcular
     * @param  list<array{weekday: int, starts: string, ends: string}>  $rules  horas locales `H:i`
     * @param  list<TimeSlot>  $taken  citas activas y bloqueos
     * @return list<TimeSlot>
     */
    public static function freeSlots(DateTimeImmutable $day, array $rules, array $taken, int $durationMinutes): array
    {
        $weekday = (int) $day->format('N');
        $free = [];

        foreach ($rules as $rule) {
            if ($rule['weekday'] !== $weekday) {
                continue;
            }

            $cursor = $day->modify($rule['starts']);
            $end = $day->modify($rule['ends']);

            for (; $cursor->modify("+{$durationMinutes} minutes") <= $end; $cursor = $cursor->modify('+'.self::STEP_MINUTES.' minutes')) {
                $slot = TimeSlot::starting($cursor, $durationMinutes);

                if (! self::collides($slot, $taken)) {
                    $free[] = $slot;
                }
            }
        }

        usort($free, fn (TimeSlot $a, TimeSlot $b): int => $a->startsAt <=> $b->startsAt);

        return $free;
    }

    /** @param list<TimeSlot> $taken */
    private static function collides(TimeSlot $slot, array $taken): bool
    {
        foreach ($taken as $busy) {
            if ($slot->overlaps($busy)) {
                return true;
            }
        }

        return false;
    }
}
