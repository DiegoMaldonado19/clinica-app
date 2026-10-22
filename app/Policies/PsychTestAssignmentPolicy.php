<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PsychTestAssignment;
use App\Models\User;

/**
 * Los resultados son contenido clinico (doc 05 §4.3): recepcion no los ve. Sin
 * create, update ni delete: se asigna con PsychTestService y nada clinico se
 * edita ni se borra.
 */
class PsychTestAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('clinical_note.view');
    }

    public function view(User $user, PsychTestAssignment $assignment): bool
    {
        return $user->hasAbility('clinical_note.view');
    }
}
