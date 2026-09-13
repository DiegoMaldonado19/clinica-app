<?php

namespace App\Providers;

use App\Auth\AbilityMatrix;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AbilityMatrix::class);
    }

    public function boot(): void
    {
        // Todo permiso `recurso.accion` del doc 05 §4.2 pasa por aqui. Devolver
        // null y no false es lo que deja seguir hasta las politicas por agregado.
        Gate::before(
            static fn (User $user, string $ability): ?bool => $user->hasAbility($ability) ? true : null
        );
    }
}
