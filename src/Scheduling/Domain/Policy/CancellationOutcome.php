<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Policy;

use App\Shared\Domain\ValueObject\Money;

final readonly class CancellationOutcome
{
    public function __construct(
        public bool $feeApplies,
        public Money $feeAmount,
        public int $feePercentage,
        public string $reasonCode,
    ) {}

    public static function free(Money $fee): self
    {
        return new self(false, new Money(0, $fee->currency), 0, 'no_fee');
    }
}
