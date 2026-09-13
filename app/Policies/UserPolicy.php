<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Gestionar personal es exclusivo de la administradora (doc 05 §4.2).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('user.create.staff');
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('user.create.staff');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasAbility('user.create.staff');
    }

    /** Baja logica: `is_active`. El borrado fisico de un usuario no existe. */
    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
