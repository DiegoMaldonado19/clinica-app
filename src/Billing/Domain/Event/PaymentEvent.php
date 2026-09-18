<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

abstract readonly class PaymentEvent implements DomainEvent
{
    public function __construct(
        public string $paymentId,
        public string $appointmentId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function aggregateId(): string
    {
        return $this->paymentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
