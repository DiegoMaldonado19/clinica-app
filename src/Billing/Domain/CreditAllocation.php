<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Shared\Domain\ValueObject\Money;

/**
 * RN-16: al abrir el cobro de una cita, el crédito a favor se consume del más
 * antiguo al más nuevo. El último puede usarse en parte; lo que falte, se paga.
 */
final readonly class CreditAllocation
{
    /** @param array<string, int> $consumed crédito => centavos que se usan de él */
    private function __construct(
        public array $consumed,
        public Money $due,
    ) {}

    /** @param array<string, int> $available crédito => centavos disponibles, del más antiguo al más nuevo */
    public static function of(Money $fee, array $available): self
    {
        $consumed = [];
        $remaining = $fee->amountInCents;

        foreach ($available as $creditId => $cents) {
            if ($remaining === 0) {
                break;
            }

            $consumed[$creditId] = min($cents, $remaining);
            $remaining -= $consumed[$creditId];
        }

        return new self($consumed, new Money($remaining, $fee->currency));
    }
}
