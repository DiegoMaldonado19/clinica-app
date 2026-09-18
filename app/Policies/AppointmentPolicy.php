<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * La cita no se edita ni se borra: cambia solo por sus transiciones de estado,
 * cada una con su propio permiso atomico.
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('appointment.view.any');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasAbility('appointment.view.any')
            || ($user->hasAbility('appointment.view.own') && $appointment->patient_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('appointment.create') && $user->hasAbility('appointment.view.any');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return false;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}
