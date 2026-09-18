<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Solo guarda los ultimos 4 digitos de la cuenta de origen: el numero completo
 * seria riesgo sin beneficio operativo (docs/fase-2 F-08).
 */
final readonly class PaymentProof
{
    public string $accountMask;

    public function __construct(
        public string $receiptNumber,
        public int $originBankId,
        string $accountLastDigits,
        public DateTimeImmutable $depositedOn,
        public string $filePath,
        public string $fileHashSha256,
        public int $fileSizeBytes,
    ) {
        if (preg_match('/^\d{4}$/', $accountLastDigits) !== 1) {
            throw new DomainException('Indica solo los últimos 4 dígitos de la cuenta de origen.');
        }

        $this->accountMask = '****'.$accountLastDigits;
    }

    public function assertPlausible(DateTimeImmutable $today): void
    {
        if ($this->depositedOn->format('Y-m-d') > $today->format('Y-m-d')) {
            throw new DomainException('La fecha del depósito no puede ser posterior a hoy.');
        }
    }
}
