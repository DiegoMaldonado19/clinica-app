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
    public static function run(callable $command, string $success): void
    {
        try {
            DB::transaction(function () use ($command): void {
                $command();
            });
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }

    public static function userCan(string $ability): bool
    {
        return (bool) auth()->user()?->hasAbility($ability);
    }
}
