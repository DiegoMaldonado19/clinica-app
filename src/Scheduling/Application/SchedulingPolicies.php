<?php

declare(strict_types=1);

namespace App\Scheduling\Application;

use App\Scheduling\Domain\Policy\ApprovalSlaPolicy;
use App\Scheduling\Domain\Policy\BookingWindowPolicy;
use App\Scheduling\Domain\Policy\TieredCancellationPolicy;
use App\Shared\Domain\BusinessRules;
use DateTimeZone;

/**
 * Construye las politicas con los parametros vigentes en cada uso: cambiar una
 * regla surte efecto en la siguiente cita, sin desplegar (CA-14).
 */
final readonly class SchedulingPolicies
{
    public function __construct(
        private BusinessRules $rules,
        public DateTimeZone $timezone,
    ) {}

    public function bookingWindow(): BookingWindowPolicy
    {
        return new BookingWindowPolicy(
            (bool) $this->rules->value('RN-01', 'same_day_booking_enabled'),
            (int) $this->rules->value('RN-02', 'min_booking_notice_hours'),
            $this->timezone,
        );
    }

    public function approvalSla(): ApprovalSlaPolicy
    {
        return new ApprovalSlaPolicy(
            (int) $this->rules->value('RN-03', 'approval_sla_hours'),
            (int) $this->rules->value('RN-03', 'approval_sla_weekend_hours'),
            $this->timezone,
        );
    }

    public function cancellation(): TieredCancellationPolicy
    {
        /** @var list<array{h: int, pct: int}> $tiers */
        $tiers = $this->rules->value('RN-06', 'cancellation_tiers');

        return TieredCancellationPolicy::fromSetting($tiers);
    }

    public function paymentDeadlineHours(): int
    {
        return (int) $this->rules->value('RN-05', 'payment_deadline_hours_before');
    }

    /** @return list<int> horas antes del inicio (RN-08) */
    public function reminderOffsets(): array
    {
        /** @var list<int> $offsets */
        $offsets = $this->rules->value('RN-08', 'reminder_offsets');

        return $offsets;
    }
}
