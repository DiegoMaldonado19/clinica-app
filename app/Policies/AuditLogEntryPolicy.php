<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLogEntry;
use App\Models\User;

/** La bitacora se consulta; no se crea, edita ni borra desde la interfaz. */
class AuditLogEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('audit.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLogEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, AuditLogEntry $entry): bool
    {
        return false;
    }
}
