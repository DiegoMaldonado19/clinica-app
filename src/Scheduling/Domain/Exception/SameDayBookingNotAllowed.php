<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Exception;

final class SameDayBookingNotAllowed extends BookingNotAllowed
{
    public function __construct()
    {
        parent::__construct('Las citas para el mismo día se coordinan por teléfono.');
    }
}
