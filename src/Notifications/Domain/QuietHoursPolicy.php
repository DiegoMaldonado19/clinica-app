<?php

declare(strict_types=1);

namespace App\Notifications\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * RN-17: dentro de la ventana de silencio el envio se difiere, nunca se pierde.
 * La ventana cruza la medianoche (21:00-07:00) y se mide en hora local.
 */
final readonly class QuietHoursPolicy
{
    public function __construct(
        private string $from,
        private string $to,
        private DateTimeZone $timezone,
    ) {}

    /** @param string $window formato de `business_rule_settings`: `21:00-07:00` */
    public static function fromSetting(string $window, DateTimeZone $timezone): self
    {
        [$from, $to] = explode('-', $window);

        return new self($from, $to, $timezone);
    }

    public function isWithin(DateTimeImmutable $moment): bool
    {
        $time = $moment->setTimezone($this->timezone)->format('H:i');

        return $this->from > $this->to
            ? $time >= $this->from || $time < $this->to
            : $time >= $this->from && $time < $this->to;
    }

    public function nextAllowed(DateTimeImmutable $moment): DateTimeImmutable
    {
        if (! $this->isWithin($moment)) {
            return $moment;
        }

        $local = $moment->setTimezone($this->timezone);
        $resume = $local->modify($this->to);

        return $resume <= $local ? $resume->modify('+1 day') : $resume;
    }
}
