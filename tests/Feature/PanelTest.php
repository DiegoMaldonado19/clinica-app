<?php

declare(strict_types=1);

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('abre el CRUD de usuarios para la administradora', function () {
    $admin = User::factory()->role('admin')->create();

    $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertSuccessful();
    $this->actingAs($admin)->get(UserResource::getUrl('create'))->assertSuccessful();
    $this->actingAs($admin)->get(UserResource::getUrl('edit', ['record' => $admin]))->assertSuccessful();
});

it('esconde el CRUD de usuarios a recepcion', function () {
    $secretary = User::factory()->role('secretary')->create();

    $this->actingAs($secretary)->get(UserResource::getUrl('index'))->assertForbidden();
});

it('abre el CRUD de pacientes para recepcion', function () {
    $secretary = User::factory()->role('secretary')->create();
    $patient = Patient::create(['id' => User::factory()->role('patient')->create()->getKey()]);

    $this->actingAs($secretary)->get(PatientResource::getUrl('index'))->assertSuccessful();
    $this->actingAs($secretary)->get(PatientResource::getUrl('create'))->assertSuccessful();
    $this->actingAs($secretary)->get(PatientResource::getUrl('edit', ['record' => $patient]))->assertSuccessful();
});

it('da de alta al usuario con credencial temporal de 72 horas', function () {
    $this->freezeTime();

    $admin = User::factory()->role('admin')->create();

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'role_id' => $admin->role_id,
            'name' => 'Recepcion Nueva',
            'email' => 'recepcion@clinica.test',
            'password' => 'Una-Contrasena-Larga-9',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $creado = User::where('email', 'recepcion@clinica.test')->sole();

    expect($creado->must_change_password)->toBeTrue()
        // Al segundo: la columna es DATETIME y no guarda microsegundos.
        ->and($creado->temp_password_expires_at->format('Y-m-d H:i:s'))
        ->toBe(now()->addHours(72)->format('Y-m-d H:i:s'));
});
