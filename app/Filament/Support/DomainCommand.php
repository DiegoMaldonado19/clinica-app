<?php

declare(strict_types=1);

namespace App\Filament\Support;

use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Como corre una accion de la interfaz: en una transaccion, para que un
 * suscriptor que falla no deje nada a medias, y con las reglas de negocio
 * devueltas como aviso en lugar de como error.
 */
final class DomainCommand
{
    /** @return bool si el cambio quedo guardado */
    public static function run(callable $command, string $success): bool
    {
        try {
            DB::transaction(function () use ($command): void {
                $command();
            });
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return false;
        }

        Notification::make()->success()->title($success)->send();

        return true;
    }

    public static function userCan(string $ability): bool
    {
        return (bool) auth()->user()?->hasAbility($ability);
    }
}
