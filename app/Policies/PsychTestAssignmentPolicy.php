<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PsychTestAssignment;
use App\Models\User;

/**
 * Resultados clinicos (doc 05 §4.3): exige clinical_note.view, que recepcion no
 * tiene. Sin create, update ni delete: se asigna con PsychTestService y nada
 * clinico se edita ni se borra.
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
