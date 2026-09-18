<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Exception;

final class BookingTooSoon extends BookingNotAllowed
{
    public function __construct()
    {
        parent::__construct('Ese horario ya no admite solicitudes en línea.');
    }
}
