<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Exception;

final class SlotUnavailable extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ese horario se acaba de ocupar.');
    }
}
