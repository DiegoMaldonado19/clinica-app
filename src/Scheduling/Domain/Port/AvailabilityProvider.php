<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Port;

use App\Scheduling\Domain\ValueObject\TimeSlot;
use DateTimeImmutable;

interface AvailabilityProvider
{
    /** @return list<TimeSlot> horarios libres de ese dia local, ya sin citas ni bloqueos */
    public function freeSlots(string $therapistId, DateTimeImmutable $day, int $durationMinutes): array;
}
