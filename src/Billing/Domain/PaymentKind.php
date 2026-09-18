<?php

declare(strict_types=1);

namespace App\Billing\Domain;

enum PaymentKind: string
{
    case SESSION_FEE = 'SESSION_FEE';
    case LATE_CANCELLATION_FEE = 'LATE_CANCELLATION_FEE';
    case NO_SHOW_FEE = 'NO_SHOW_FEE';
}
