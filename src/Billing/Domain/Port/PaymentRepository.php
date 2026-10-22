<?php

declare(strict_types=1);

namespace App\Billing\Domain\Port;

use App\Billing\Domain\CreditAllocation;
use App\Billing\Domain\DuplicateReceipt;
use App\Billing\Domain\Payment;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

interface PaymentRepository
{
    public function nextId(): string;

    public function find(string $id): ?Payment;

    public function findSessionPayment(string $appointmentId): ?Payment;

    /** @throws DuplicateReceipt si la boleta ya existe en ese banco (RN-14) */
    public function save(Payment $payment): void;

    /** RN-16: credito a favor del paciente de la cita, nunca reembolso bancario. */
    public function issueCredit(string $appointmentId, Money $amount, DateTimeImmutable $at): string;

    /** @return array<string, int> credito => centavos sin usar, del mas antiguo al mas nuevo */
    public function availableCredits(string $patientId): array;

    public function consumeCredits(CreditAllocation $allocation, string $appointmentId, DateTimeImmutable $at): void;

    /** Centavos de credito que consumio la cita: cuentan como pagados si se cancela. */
    public function creditUsedBy(string $appointmentId): int;
}
