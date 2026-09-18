<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Exception;

use DomainException;

/** RN-01 / RN-02: el horario existe, pero no se puede pedir por esta via. */
abstract class BookingNotAllowed extends DomainException {}
