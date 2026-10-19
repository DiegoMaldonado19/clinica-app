<?php

declare(strict_types=1);

use App\Scheduling\Domain\AvailabilityCalculator;
use App\Scheduling\Domain\ValueObject\TimeSlot;

it('quita del ultimo dia de un bloqueo solo los horarios que ocupa', function () {
    $utc = new DateTimeZone('UTC');
    $friday = new DateTimeImmutable('2026-09-25 00:00', new DateTimeZone('America/Guatemala'));
    $rules = [['weekday' => 5, 'starts' => '08:00', 'ends' => '12:00'], ['weekday' => 5, 'starts' => '15:00', 'ends' => '19:00']];
    // Lunes 15:00 a viernes 12:00 en Guatemala, en UTC como llega de la base.
    $block = new TimeSlot(new DateTimeImmutable('2026-09-21 21:00', $utc), new DateTimeImmutable('2026-09-25 18:00', $utc));

    $free = AvailabilityCalculator::freeSlots($friday, $rules, [$block], 50);

    expect(array_map(fn (TimeSlot $slot): string => $slot->startsAt->format('H:i'), $free))
        ->toBe(['15:00', '16:00', '17:00', '18:00']);
});
