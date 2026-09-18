<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Policy;

use App\Scheduling\Domain\Exception\BookingTooSoon;
use App\Scheduling\Domain\Exception\SameDayBookingNotAllowed;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use DateTimeImmutable;
use DateTimeZone;

/** RN-01 y RN-02. El "mismo dia" se juzga en la hora local de la clinica. */
final readonly class BookingWindowPolicy
{
    public function __construct(
        private bool $sameDayBookingEnabled,
        private int $minNoticeHours,
        private DateTimeZone $timezone,
    ) {}

    public function assertBookable(TimeSlot $slot, DateTimeImmutable $now): void
    {
        $sameDay = $slot->startsAt->setTimezone($this->timezone)->format('Y-m-d')
            === $now->setTimezone($this->timezone)->format('Y-m-d');

        if ($sameDay && ! $this->sameDayBookingEnabled) {
            throw new SameDayBookingNotAllowed;
        }

        if ($slot->secondsFrom($now) < $this->minNoticeHours * 3600) {
            throw new BookingTooSoon;
        }
    }
}
