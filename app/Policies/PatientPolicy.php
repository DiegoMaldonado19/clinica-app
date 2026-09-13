<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('patient.view.any');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->hasAbility('patient.view.any') || $user->getKey() === $patient->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('patient.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasAbility('patient.update');
    }

    /** El expediente no se borra (doc 06 §7). */
    public function delete(User $user, Patient $patient): bool
    {
        return false;
    }
}
