<?php

declare(strict_types=1);

use App\Filament\Auth\RequestPasswordReset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('deja entrar al panel al personal', function () {
    $admin = User::factory()->role('admin')->create();

    $this->actingAs($admin)->get('/admin')->assertSuccessful();
});

it('no deja entrar al panel a un paciente', function () {
    $patient = User::factory()->role('patient')->create();

    $this->actingAs($patient)->get('/admin')->assertForbidden();
});

it('no deja entrar al panel a personal dado de baja', function () {
    $inactive = User::factory()->role('secretary')->create(['is_active' => false]);

    $this->actingAs($inactive)->get('/admin')->assertForbidden();
});

it('manda al invitado al inicio de sesion', function () {
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});

it('cierra las sesiones abiertas al restablecer la contrasena', function () {
    $user = User::factory()->role('admin')->create();

    DB::table('sessions')->insert([
        ['id' => 'suya', 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => time()],
        ['id' => 'ajena', 'user_id' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    event(new PasswordReset($user));

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['ajena']);
});

// Mismo mensaje y mismo formulario vacio en ambos casos: ni el texto ni el
// estado de la pantalla revelan si el correo esta registrado (doc 05 §6).
it('responde lo mismo exista o no la cuenta', function (bool $existe) {
    Notification::fake();

    $user = User::factory()->role('admin')->create();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $existe ? $user->email : 'nadie@ejemplo.test'])
        ->call('request')
        ->assertNotified(__(Password::RESET_LINK_SENT))
        ->assertFormSet(['email' => null]);

    // Pero solo la cuenta que existe recibe el enlace.
    $existe
        ? Notification::assertSentTo($user, ResetPassword::class)
        : Notification::assertNothingSent();
})->with([true, false]);

it('caduca el enlace de recuperacion a los 60 minutos', function () {
    expect(config('auth.passwords.users.expire'))->toBe(60);
});

it('obliga a cambiar la contrasena temporal antes de usar el panel', function () {
    $user = User::factory()->role('admin')->withTemporaryPassword()->create();

    $this->actingAs($user)->get('/admin')->assertRedirect(route('password.change'));
});

it('cierra la sesion si la contrasena temporal ya vencio', function () {
    $user = User::factory()->role('admin')->withTemporaryPassword('-1 hour')->create();

    $this->actingAs($user)->get('/admin')->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});

it('libera el panel al guardar la contrasena nueva', function () {
    $user = User::factory()->role('admin')->withTemporaryPassword()->create();

    $this->actingAs($user)
        ->post(route('password.change.update'), [
            'password' => 'Una-Contrasena-Larga-9',
            'password_confirmation' => 'Una-Contrasena-Larga-9',
        ])
        ->assertRedirect();

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and($user->temp_password_expires_at)->toBeNull();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});
