<?php

declare(strict_types=1);

namespace App\Billing\Domain\Port;

/**
 * Factura electrónica en línea (FEL) de la SAT. Fuera del alcance de la Fase 3:
 * hoy la implementa un adaptador nulo; la guía de F3-07 describe la real.
 */
interface FiscalDocumentIssuer
{
    /** Emite el documento tributario del pago aprobado; el adaptador real guarda su número de autorización. */
    public function issueFor(string $paymentId): void;
}
