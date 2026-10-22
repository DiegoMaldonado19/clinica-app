<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/** RF-63: la base de conocimiento del asistente es un parametro de la clinica. */
class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('settings.manage');
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->hasAbility('settings.manage');
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->hasAbility('settings.manage');
    }

    public function reorder(User $user): bool
    {
        return $user->hasAbility('settings.manage');
    }
}
