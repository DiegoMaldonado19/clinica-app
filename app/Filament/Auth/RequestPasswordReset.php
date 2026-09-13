<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Doc 05 §6: la respuesta es identica exista o no la cuenta. De serie, Filament
 * responde con una notificacion de error cuando el correo no existe, que es un
 * oraculo de cuentas registradas.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getFailureNotification(string $status): ?Notification
    {
        if (! in_array($status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return parent::getFailureNotification($status);
        }

        // El formulario tambien se limpia: dejarlo con el correo escrito
        // distinguiria este caso del exito tan bien como el mensaje.
        $this->form->fill();

        return $this->getSentNotification(Password::RESET_LINK_SENT);
    }
}
