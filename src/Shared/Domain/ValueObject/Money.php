<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $amountInCents,
        public string $currency = 'GTQ',
    ) {
        if ($amountInCents < 0) {
            throw new InvalidArgumentException('Un monto no puede ser negativo.');
        }
    }

    public static function gtq(int $amountInCents): self
    {
        return new self($amountInCents);
    }

    /** Redondeo hacia abajo al centavo: el cargo nunca excede el porcentaje. */
    public function percentage(int $percentage): self
    {
        return new self(intdiv($this->amountInCents * $percentage, 100), $this->currency);
    }

    public function subtract(self $other): self
    {
        return new self(max(0, $this->amountInCents - $other->amountInCents), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function format(): string
    {
        $decimals = $this->amountInCents % 100 === 0 ? 0 : 2;

        return 'Q '.number_format($this->amountInCents / 100, $decimals);
    }
}
