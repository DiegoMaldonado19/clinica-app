<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

enum BookingSource: string
{
    case WEB = 'web';
    case CHAT = 'chat';
    case PHONE = 'phone';
    case WALK_IN = 'walk_in';
}
