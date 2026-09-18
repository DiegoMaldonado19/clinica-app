<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function loginOn(string $panel, string $email): Testable
{
    Filament::setCurrentPanel(Filament::getPanel($panel));

    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => 'password'])
        ->call('authenticate');
}

it('deja entrar al personal por el formulario del panel', function (string $role) {
    $user = User::factory()->role($role)->create();

    loginOn('admin', $user->email)->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
})->with(['admin', 'secretary']);

it('deja entrar al paciente por el formulario del portal', function () {
    $user = User::factory()->role('patient')->create();

    loginOn('portal', $user->email)->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('no deja al personal entrar por el portal ni al paciente por el panel', function (string $panel, string $role) {
    $user = User::factory()->role($role)->create();

    loginOn($panel, $user->email)->assertHasFormErrors(['email']);

    $this->assertGuest();
})->with([['portal', 'admin'], ['admin', 'patient']]);

it('ignora un destino pendiente del otro panel al iniciar sesion', function () {
    $user = User::factory()->role('admin')->create();
    session(['url.intended' => url('/portal')]); // visito el portal sin sesion antes de ir al panel

    loginOn('admin', $user->email)->assertRedirect(Filament::getPanel('admin')->getUrl());
});

it('respeta el destino pendiente dentro del mismo panel', function () {
    $user = User::factory()->role('admin')->create();
    session(['url.intended' => url('/admin/payments')]);

    loginOn('admin', $user->email)->assertRedirect(url('/admin/payments'));
});

it('ofrece en el login del portal el acceso del personal', function () {
    $this->get('/portal/login')
        ->assertSuccessful()
        ->assertSee('¿Eres del personal?')
        ->assertSee(Filament::getPanel('admin')->getLoginUrl(), escape: false);
});
