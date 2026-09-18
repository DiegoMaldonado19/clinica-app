<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/** Nada financiero se crea a mano ni se borra (doc 06 §7). */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('payment.view.any');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasAbility('payment.view.any');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }
}
