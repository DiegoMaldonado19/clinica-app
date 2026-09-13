<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Los permisos de un rol, leidos de `role_ability`.
 *
 * Se resuelve la primera vez que alguien pregunta, no en el arranque: definir
 * los 32 `Gate` al bootear obligaria a consultar la base en cada peticion, y
 * romperia `artisan migrate` sobre una base vacia.
 *
 * Se memoriza por peticion y no en la cache compartida: una consulta indexada
 * por peticion es gratis, y un TTL dejaria funcionando durante una hora un
 * permiso ya revocado.
 */
final class AbilityMatrix
{
    /** @var array<int, list<string>> */
    private array $resolved = [];

    /** @return list<string> */
    public function for(int $roleId): array
    {
        return $this->resolved[$roleId] ??= DB::table('role_ability')
            ->join('abilities', 'abilities.id', '=', 'role_ability.ability_id')
            ->where('role_ability.role_id', $roleId)
            ->where('abilities.is_active', true)
            ->pluck('abilities.code')
            ->all();
    }
}
