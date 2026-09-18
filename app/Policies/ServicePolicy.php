<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/** Tarifas y servicios son parametros del negocio (MU-15). */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('settings.manage');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasAbility('settings.manage');
    }

    /** Un servicio con citas no se borra: se desactiva. */
    public function delete(User $user, Service $service): bool
    {
        return false;
    }
}
