<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure;

use App\Billing\Domain\Port\FiscalDocumentIssuer;

/** Sin certificador FEL contratado: el pago se aprueba igual y no se emite documento. */
final class NullFiscalDocumentIssuer implements FiscalDocumentIssuer
{
    public function issueFor(string $paymentId): void {}
}
