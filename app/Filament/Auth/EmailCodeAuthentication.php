<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Shared\Domain\BusinessRules;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;

/** El codigo por correo de la MFA de Filament, con la vigencia de RN-15 en lugar de una constante. */
final class EmailCodeAuthentication extends EmailAuthentication
{
    public function __construct(private readonly BusinessRules $rules) {}

    public function getCodeExpiryMinutes(): int
    {
        return (int) $this->rules->value('RN-15', 'otp_ttl_minutes');
    }
}
