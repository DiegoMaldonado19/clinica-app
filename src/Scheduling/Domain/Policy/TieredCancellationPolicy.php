<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Policy;

use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

/**
 * RN-06. Los tramos se comparan con `>=`: exactamente 24 h es sin cargo y
 * exactamente 1 h es 50 %. Despues del inicio no aplica ningun tramo: 100 %.
 */
final readonly class TieredCancellationPolicy
{
    /** @var list<CancellationTier> */
    private array $tiers;

    /** @param list<CancellationTier> $tiers */
    public function __construct(array $tiers)
    {
        usort($tiers, fn (CancellationTier $a, CancellationTier $b): int => $b->minHoursBefore <=> $a->minHoursBefore);
        $this->tiers = $tiers;
    }

    /** @param list<array{h: int, pct: int}> $setting el JSON de `business_rule_settings` */
    public static function fromSetting(array $setting): self
    {
        return new self(array_map(
            fn (array $tier): CancellationTier => new CancellationTier((int) $tier['h'], (int) $tier['pct']),
            $setting,
        ));
    }

    public function evaluate(TimeSlot $slot, Money $fee, DateTimeImmutable $now): CancellationOutcome
    {
        $secondsBefore = $slot->secondsFrom($now);
        $percentage = 100;

        foreach ($this->tiers as $tier) {
            if ($secondsBefore >= $tier->minHoursBefore * 3600) {
                $percentage = $tier->feePercentage;
                break;
            }
        }

        if ($percentage === 0) {
            return CancellationOutcome::free($fee);
        }

        return new CancellationOutcome(true, $fee->percentage($percentage), $percentage, "late_{$percentage}");
    }
}
