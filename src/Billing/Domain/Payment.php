<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Billing\Domain\Event\LateCancellationFeeApplied;
use App\Billing\Domain\Event\LateCancellationFeeWaived;
use App\Billing\Domain\Event\PaymentApproved;
use App\Billing\Domain\Event\PaymentProofSubmitted;
use App\Billing\Domain\Event\PaymentRejected;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;
use DomainException;

/**
 * RN-09: el sistema valida, la persona decide. Ningun metodo aprueba solo.
 */
final class Payment
{
    /** @var list<DomainEvent> */
    private array $events = [];

    private ?PaymentProof $newProof = null;

    private function __construct(
        public readonly string $id,
        public readonly string $appointmentId,
        public readonly PaymentKind $kind,
        public readonly Money $amount,
        public readonly ?int $feePercentage,
        private PaymentStatus $status,
        private ?string $method = null,
        private ?string $reviewedBy = null,
        private ?string $rejectionReason = null,
        private ?string $waivedBy = null,
        private ?string $waiverReason = null,
    ) {}

    public static function forSessionFee(string $id, string $appointmentId, Money $amount): self
    {
        return new self($id, $appointmentId, PaymentKind::SESSION_FEE, $amount, null, PaymentStatus::PENDIENTE);
    }

    /**
     * Cargo por cancelacion tardia. Si la sesion ya estaba pagada, el cargo nace
     * cubierto por ese pago y el resto vuelve como credito: nunca se cobra dos veces.
     */
    public static function forLateCancellation(string $id, string $appointmentId, Money $amount, int $percentage, bool $coveredBySessionPayment, ClockInterface $clock): self
    {
        $payment = new self(
            $id, $appointmentId, PaymentKind::LATE_CANCELLATION_FEE, $amount, $percentage,
            $coveredBySessionPayment ? PaymentStatus::APROBADO : PaymentStatus::PENDIENTE,
        );
        $payment->events[] = new LateCancellationFeeApplied($id, $appointmentId, $clock->now(), $amount->amountInCents, $percentage);

        return $payment;
    }

    public static function restore(
        string $id,
        string $appointmentId,
        PaymentKind $kind,
        Money $amount,
        ?int $feePercentage,
        PaymentStatus $status,
        ?string $method,
        ?string $reviewedBy,
        ?string $rejectionReason,
        ?string $waivedBy,
        ?string $waiverReason,
    ): self {
        return new self($id, $appointmentId, $kind, $amount, $feePercentage, $status, $method, $reviewedBy, $rejectionReason, $waivedBy, $waiverReason);
    }

    public function submitProof(PaymentProof $proof, ClockInterface $clock): void
    {
        $proof->assertPlausible($clock->now());
        $this->transition(PaymentStatus::EN_REVISION);
        $this->method = 'TRANSFERENCIA';
        $this->newProof = $proof;
        $this->events[] = new PaymentProofSubmitted($this->id, $this->appointmentId, $clock->now(), $this->amount->amountInCents);
    }

    public function approve(string $reviewedBy, ClockInterface $clock): void
    {
        $this->transition(PaymentStatus::APROBADO);
        $this->reviewedBy = $reviewedBy;
        $this->events[] = new PaymentApproved($this->id, $this->appointmentId, $clock->now());
    }

    public function reject(string $reviewedBy, string $reason, ClockInterface $clock): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Rechazar un comprobante exige un motivo.');
        }

        $this->transition(PaymentStatus::RECHAZADO);
        $this->reviewedBy = $reviewedBy;
        $this->rejectionReason = $reason;
        $this->events[] = new PaymentRejected($this->id, $this->appointmentId, $clock->now(), $reason);
    }

    public function expectAtDesk(): void
    {
        $this->transition(PaymentStatus::EN_CAJA);
        $this->method = 'EFECTIVO';
    }

    /** Cobro en efectivo al registrar la llegada: no hay comprobante que revisar. */
    public function collectAtDesk(string $collectedBy): void
    {
        $this->transition(PaymentStatus::APROBADO);
        $this->reviewedBy = $collectedBy;
    }

    /** RF-26: solo cargos, con motivo. Devuelve si el cargo ya estaba cubierto. */
    public function waive(string $waivedBy, string $reason, ClockInterface $clock): bool
    {
        if ($this->kind === PaymentKind::SESSION_FEE) {
            throw new DomainException('Solo se exonera un cargo, no la tarifa de la sesión.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Exonerar un cargo exige un motivo.');
        }

        $wasCovered = $this->status === PaymentStatus::APROBADO;
        $this->transition(PaymentStatus::EXONERADO);
        $this->waivedBy = $waivedBy;
        $this->waiverReason = $reason;
        $this->events[] = new LateCancellationFeeWaived($this->id, $this->appointmentId, $clock->now());

        return $wasCovered;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function method(): ?string
    {
        return $this->method;
    }

    public function reviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function waivedBy(): ?string
    {
        return $this->waivedBy;
    }

    public function waiverReason(): ?string
    {
        return $this->waiverReason;
    }

    public function releaseProof(): ?PaymentProof
    {
        [$proof, $this->newProof] = [$this->newProof, null];

        return $proof;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        [$events, $this->events] = [$this->events, []];

        return $events;
    }

    private function transition(PaymentStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new DomainException("El pago no puede pasar de {$this->status->value} a {$to->value}.");
        }

        $this->status = $to;
    }
}
