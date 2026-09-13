<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('concede y niega segun la matriz del doc 05 §4.2', function (string $role, string $ability, bool $allowed) {
    $user = User::factory()->role($role)->create();

    expect(Gate::forUser($user)->allows($ability))->toBe($allowed);
})->with([
    // Recepcion aprueba citas y pagos...
    ['secretary', 'appointment.approve', true],
    ['secretary', 'payment.approve', true],
    // ...pero no toca contenido clinico, dinero exonerado ni reportes financieros.
    ['secretary', 'report.view.financial', false],
    ['secretary', 'clinical_note.view', false],
    ['secretary', 'payment.waive_fee', false],
    ['secretary', 'schedule.block', false],
    ['secretary', 'settings.manage', false],
    ['secretary', 'audit.view', false],

    ['admin', 'report.view.financial', true],
    ['admin', 'clinical_note.seal', true],
    ['admin', 'settings.manage', true],
    // Responder una prueba es del paciente, no de la administradora.
    ['admin', 'psych_test.answer', false],

    ['patient', 'appointment.cancel.own', true],
    ['patient', 'psych_test.answer', true],
    ['patient', 'appointment.view.any', false],
    ['patient', 'patient.view.any', false],
]);

it('niega un permiso que no existe en el catalogo', function () {
    $admin = User::factory()->role('admin')->create();

    expect(Gate::forUser($admin)->allows('inventado.permiso'))->toBeFalse();
});

it('deja la gestion de personal solo a la administradora', function (string $role, bool $allowed) {
    $user = User::factory()->role($role)->create();

    expect(Gate::forUser($user)->allows('viewAny', User::class))->toBe($allowed);
})->with([
    ['admin', true],
    ['secretary', false],
    ['patient', false],
]);

it('no permite borrar un usuario ni siquiera a la administradora', function () {
    $admin = User::factory()->role('admin')->create();
    $otro = User::factory()->role('secretary')->create();

    expect(Gate::forUser($admin)->allows('delete', $otro))->toBeFalse();
});
