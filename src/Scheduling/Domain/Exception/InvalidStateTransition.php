<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Exception;

use App\Scheduling\Domain\Model\AppointmentStatus;
use DomainException;

final class InvalidStateTransition extends DomainException
{
    public function __construct(AppointmentStatus $from, AppointmentStatus $to)
    {
        parent::__construct("La cita no puede pasar de {$from->value} a {$to->value}.");
    }
}
