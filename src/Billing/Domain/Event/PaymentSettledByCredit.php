<?php

declare(strict_types=1);

namespace App\Billing\Domain\Event;

/** RN-16: el crédito a favor cubrió toda la tarifa; la cita queda agendada sin comprobante. */
final readonly class PaymentSettledByCredit extends PaymentEvent {}
