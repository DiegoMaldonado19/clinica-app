<?php

declare(strict_types=1);

namespace App\Billing\Domain\Port;

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
}
