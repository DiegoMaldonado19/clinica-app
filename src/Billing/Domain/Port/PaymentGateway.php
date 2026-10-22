<?php

declare(strict_types=1);

namespace App\Billing\Domain\Port;

use App\Shared\Domain\ValueObject\Money;

/** Pasarela de pago con tarjeta. Fuera de alcance: adaptador nulo y guía en F3-07. */
interface PaymentGateway
{
    public function isAvailable(): bool;

    /** Adónde se envía a la persona para pagar el cobro. Solo se llama si isAvailable(). */
    public function checkoutUrl(string $paymentId, Money $amount): string;
}
