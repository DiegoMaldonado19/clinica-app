<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TimeSlot
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('Un horario debe terminar despues de empezar.');
        }
    }

    public static function starting(DateTimeImmutable $startsAt, int $minutes): self
    {
        return new self($startsAt, $startsAt->modify("+{$minutes} minutes"));
    }

    public function overlaps(self $other): bool
    {
        return $this->startsAt < $other->endsAt && $other->startsAt < $this->endsAt;
    }

    public function secondsFrom(DateTimeImmutable $moment): int
    {
        return $this->startsAt->getTimestamp() - $moment->getTimestamp();
    }
}
