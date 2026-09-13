<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;

/**
 * Doc 05 §6: restablecer la contrasena cierra las sesiones abiertas. Es la
 * unica parte del flujo que Laravel no trae de serie, y es justo la que importa
 * cuando el motivo del restablecimiento fue una cuenta comprometida.
 */
class InvalidateSessionsAfterPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        DB::table('sessions')
            ->where('user_id', $event->user->getAuthIdentifier())
            ->delete();
    }
}
