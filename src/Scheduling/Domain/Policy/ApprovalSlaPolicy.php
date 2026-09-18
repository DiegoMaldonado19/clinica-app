<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Policy;

use App\Scheduling\Domain\ValueObject\TimeSlot;
use DateTimeImmutable;
use DateTimeZone;

/**
 * RN-03. La solicitud hecha en fin de semana tiene el plazo largo. El plazo
 * nunca pasa del inicio de la cita: aprobar despues de empezar no tiene sentido.
 */
final readonly class ApprovalSlaPolicy
{
    public function __construct(
        private int $weekdayHours,
        private int $weekendHours,
        private DateTimeZone $timezone,
    ) {}

    public function deadlineFor(DateTimeImmutable $requestedAt, TimeSlot $slot): DateTimeImmutable
    {
        $isWeekend = (int) $requestedAt->setTimezone($this->timezone)->format('N') >= 6;
        $hours = $isWeekend ? $this->weekendHours : $this->weekdayHours;

        return min($requestedAt->modify("+{$hours} hours"), $slot->startsAt);
    }
}
