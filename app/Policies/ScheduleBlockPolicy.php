<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScheduleBlock;
use App\Models\User;

class ScheduleBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('schedule.block');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('schedule.block');
    }

    public function update(User $user, ScheduleBlock $block): bool
    {
        return false;
    }

    public function delete(User $user, ScheduleBlock $block): bool
    {
        return $user->hasAbility('schedule.block');
    }
}
