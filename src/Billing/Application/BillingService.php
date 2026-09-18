<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Domain\Event\PatientCreditIssued;
use App\Billing\Domain\Payment;
use App\Billing\Domain\PaymentProof;
use App\Billing\Domain\PaymentStatus;
use App\Billing\Domain\Port\PaymentRepository;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\ValueObject\Money;
use DomainException;

final readonly class BillingService
{
    public function __construct(
        private PaymentRepository $payments,
        private EventBus $events,
        private ClockInterface $clock,
    ) {}

    /** Idempotente: aprobar dos veces la misma cita no abre dos cobros. */
    public function openSessionPayment(string $appointmentId, Money $fee): void
    {
        if ($this->payments->findSessionPayment($appointmentId) === null) {
            $this->payments->save(Payment::forSessionFee($this->payments->nextId(), $appointmentId, $fee));
        }
    }

    public function submitProof(string $appointmentId, PaymentProof $proof): void
    {
        $payment = $this->sessionPayment($appointmentId);
        $payment->submitProof($proof, $this->clock);
        $this->persist($payment);
    }

    public function approve(string $paymentId, string $reviewedBy): void
    {
        $payment = $this->load($paymentId);
        $payment->approve($reviewedBy, $this->clock);
        $this->persist($payment);
    }

    public function reject(string $paymentId, string $reviewedBy, string $reason): void
    {
        $payment = $this->load($paymentId);
        $payment->reject($reviewedBy, $reason, $this->clock);
        $this->persist($payment);
    }

    public function expectAtDesk(string $appointmentId): void
    {
        $payment = $this->sessionPayment($appointmentId);
        $payment->expectAtDesk();
        $this->persist($payment);
    }

    public function collectAtDesk(string $appointmentId, string $collectedBy): void
    {
        $payment = $this->sessionPayment($appointmentId);
        $payment->collectAtDesk($collectedBy);
        $this->persist($payment);
    }

    /**
     * RN-06 + RN-16. Pagada: el cargo sale de lo pagado y el resto es credito.
     * Sin pagar: el cargo queda pendiente de cobro.
     */
    public function settleCancellation(string $appointmentId, int $feeCents, int $feePercentage): void
    {
        $session = $this->payments->findSessionPayment($appointmentId);
        $paid = $session?->status() === PaymentStatus::APROBADO;
        $fee = new Money($feeCents, $session !== null ? $session->amount->currency : 'GTQ');

        if (! $fee->isZero()) {
            $this->persist(Payment::forLateCancellation(
                $this->payments->nextId(), $appointmentId, $fee, $feePercentage, $paid, $this->clock,
            ));
        }

        if ($paid) {
            $this->issueCredit($appointmentId, $session->amount->subtract($fee));
        }
    }

    public function waive(string $paymentId, string $waivedBy, string $reason): void
    {
        $payment = $this->load($paymentId);
        $wasCovered = $payment->waive($waivedBy, $reason, $this->clock);
        $this->persist($payment);

        if ($wasCovered) {
            $this->issueCredit($payment->appointmentId, $payment->amount);
        }
    }

    private function issueCredit(string $appointmentId, Money $amount): void
    {
        if ($amount->isZero()) {
            return;
        }

        $creditId = $this->payments->issueCredit($appointmentId, $amount, $this->clock->now());
        $this->events->publish(new PatientCreditIssued($creditId, $appointmentId, $this->clock->now(), $amount->amountInCents));
    }

    private function persist(Payment $payment): void
    {
        $this->payments->save($payment);
        $this->events->publish(...$payment->releaseEvents());
    }

    private function load(string $paymentId): Payment
    {
        return $this->payments->find($paymentId) ?? throw new DomainException('El pago no existe.');
    }

    private function sessionPayment(string $appointmentId): Payment
    {
        return $this->payments->findSessionPayment($appointmentId)
            ?? throw new DomainException('La cita no tiene un cobro abierto.');
    }
}
