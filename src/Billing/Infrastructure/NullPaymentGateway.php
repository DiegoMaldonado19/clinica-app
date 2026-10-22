<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure;

use App\Billing\Domain\Port\PaymentGateway;
use App\Shared\Domain\ValueObject\Money;
use LogicException;

/** Sin pasarela contratada: el portal no ofrece pagar con tarjeta. */
final class NullPaymentGateway implements PaymentGateway
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function checkoutUrl(string $paymentId, Money $amount): string
    {
        throw new LogicException('No hay pasarela de pago configurada.');
    }
}
