<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence;

use App\Scheduling\Domain\AvailabilityCalculator;
use App\Scheduling\Domain\Port\AvailabilityProvider;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Se cachean horarios, nunca personas (doc 05 §4.4): la clave y el valor solo
 * llevan fechas. Toda escritura que ocupa o libera un dia llama a `forget`.
 */
final class DatabaseAvailabilityProvider implements AvailabilityProvider
{
    private const TTL_SECONDS = 300;

    public function freeSlots(string $therapistId, DateTimeImmutable $day, int $durationMinutes): array
    {
        // Marcas de tiempo y no objetos: la cache no deserializa clases propias
        // (`cache.serializable_classes`), y asi no hay nada que explotar al leerla.
        /** @var list<array{0: int, 1: int}> $cached */
        $cached = Cache::remember(
            self::key($therapistId, $day, $durationMinutes),
            self::TTL_SECONDS,
            fn (): array => array_map(
                fn (TimeSlot $slot): array => [$slot->startsAt->getTimestamp(), $slot->endsAt->getTimestamp()],
                $this->compute($therapistId, $day, $durationMinutes),
            ),
        );

        $timezone = $day->getTimezone();

        return array_map(fn (array $slot): TimeSlot => new TimeSlot(
            (new DateTimeImmutable("@{$slot[0]}"))->setTimezone($timezone),
            (new DateTimeImmutable("@{$slot[1]}"))->setTimezone($timezone),
        ), $cached);
    }

    /** Invalida el dia local de ese instante para todas las duraciones de servicio. */
    public static function forget(string $therapistId, DateTimeImmutable $startsAt, DateTimeZone $timezone): void
    {
        $day = $startsAt->setTimezone($timezone)->setTime(0, 0);

        foreach (DB::table('services')->distinct()->pluck('duration_minutes') as $minutes) {
            Cache::forget(self::key($therapistId, $day, (int) $minutes));
        }
    }

    /** @return list<TimeSlot> */
    private function compute(string $therapistId, DateTimeImmutable $day, int $durationMinutes): array
    {
        $from = $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $to = $day->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $rules = DB::table('availability_rules')
            ->where('therapist_id', $therapistId)
            ->get(['weekday', 'starts_at', 'ends_at'])
            ->map(fn (object $rule): array => [
                'weekday' => (int) $rule->weekday,
                'starts' => substr($rule->starts_at, 0, 5),
                'ends' => substr($rule->ends_at, 0, 5),
            ])
            ->all();

        $appointments = DB::table('appointments')
            ->where('therapist_id', $therapistId)
            ->whereNotNull('active_slot')
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        $blocks = DB::table('schedule_blocks')
            ->where('therapist_id', $therapistId)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        $taken = $appointments->concat($blocks)->map(fn (object $row): TimeSlot => new TimeSlot(
            new DateTimeImmutable($row->starts_at, new DateTimeZone('UTC')),
            new DateTimeImmutable($row->ends_at, new DateTimeZone('UTC')),
        ))->values()->all();

        return AvailabilityCalculator::freeSlots($day, array_values($rules), $taken, $durationMinutes);
    }

    private static function key(string $therapistId, DateTimeImmutable $day, int $durationMinutes): string
    {
        return "availability:{$therapistId}:{$day->format('Y-m-d')}:{$durationMinutes}";
    }
}
