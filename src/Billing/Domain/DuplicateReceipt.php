<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use DomainException;

/** RN-14: la misma boleta no puede registrarse dos veces en el mismo banco. */
final class DuplicateReceipt extends DomainException
{
    public function __construct()
    {
        parent::__construct('Ese número de boleta ya fue registrado en otro pago. Verifícalo o comunícate con nosotros.');
    }
}
