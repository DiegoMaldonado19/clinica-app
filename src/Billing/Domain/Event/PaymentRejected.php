<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

use DateTimeImmutable;

final readonly class PaymentRejected extends PaymentEvent
{
    public function __construct(
        string $paymentId,
        string $appointmentId,
        DateTimeImmutable $occurredAt,
        public string $reason,
    ) {
        parent::__construct($paymentId, $appointmentId, $occurredAt);
    }
}
