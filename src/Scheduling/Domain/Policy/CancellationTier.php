<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Policy;

final readonly class CancellationTier
{
    public function __construct(
        public int $minHoursBefore,
        public int $feePercentage,
    ) {}
}
