<?php

declare(strict_types=1);

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\EmailCodeAuthentication;
use App\Filament\Auth\Login;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Notifications\VerifyEmailChange;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Notification::fake();
});

/** Correo y contrasena correctos: con el segundo factor activo, Filament responde con el reto. */
function signIn(string $panel, User $user): Testable
{
    Filament::setCurrentPanel(Filament::getPanel($panel));

    return Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');
}

function emailedCode(User $user): string
{
    $code = '';
    Notification::assertSentTo($user, VerifyEmailAuthentication::class, function (VerifyEmailAuthentication $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('manda al personal sin segundo factor a configurarlo antes de usar el panel', function () {
    $admin = User::factory()->role('admin')->create(['two_factor_enabled' => false]);

    $this->actingAs($admin)->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('no deja entrar al personal hasta que escribe el codigo que llega por correo', function () {
    $admin = User::factory()->role('admin')->create();

    $login = signIn('admin', $admin);
    $this->assertGuest();

    $login->set('data.multiFactor.email_code.code', emailedCode($admin))
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($admin);
});

it('registra en la bitacora un codigo incorrecto y no deja entrar', function () {
    $secretary = User::factory()->role('secretary')->create();

    $login = signIn('admin', $secretary);
    $wrong = str_pad((string) (((int) emailedCode($secretary) + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

    $login->set('data.multiFactor.email_code.code', $wrong)
        ->call('authenticate')
        ->assertHasErrors(['data.multiFactor.email_code.code']);

    $this->assertGuest();
    expect(DB::table('audit_log')->where('action', 'two_factor.failed')->value('subject_id'))->toBe($secretary->getKey());
});

it('deja al paciente usar el portal sin segundo factor', function () {
    $patient = User::factory()->role('patient')->create();

    $this->actingAs($patient)->get('/portal/mis-citas')->assertSuccessful();
});

it('pide el segundo factor al paciente que lo activo', function () {
    $patient = User::factory()->role('patient')->create(['two_factor_enabled' => true]);

    signIn('portal', $patient);

    $this->assertGuest();
    Notification::assertSentTo($patient, VerifyEmailAuthentication::class);
});

function withBothFactors(User $user): User
{
    $user->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

    return $user;
}

// F3-10: con los dos factores, la app va primero y el correo sale solo si se elige.
it('con los dos factores no manda el correo hasta que la persona lo elige', function () {
    $admin = withBothFactors(User::factory()->role('admin')->create());

    $login = signIn('admin', $admin)->assertSet('data.multiFactor.provider', 'app');
    Notification::assertNotSentTo($admin, VerifyEmailAuthentication::class);

    $login->set('data.multiFactor.provider', 'email_code');

    Notification::assertSentToTimes($admin, VerifyEmailAuthentication::class, 1);
});

it('con los dos factores entra con la aplicacion sin recibir ningun correo', function () {
    $admin = withBothFactors(User::factory()->role('admin')->create());

    signIn('admin', $admin)
        ->set('data.multiFactor.app.code', AppAuthentication::make()->getCurrentCode($admin))
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($admin);
    Notification::assertNotSentTo($admin, VerifyEmailAuthentication::class);
});

it('ofrece en el perfil la gestion del segundo factor', function (string $panel, string $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(Filament::getPanel($panel)->getProfileUrl())
        ->assertSuccessful()
        ->assertSee('Aplicación de autenticación')
        ->assertSee('Códigos de verificación por correo electrónico');
})->with([['admin', 'admin'], ['portal', 'patient']]);

// Si el perfil cambiara el correo al instante, una sesion robada se llevaria tambien el factor por correo.
it('no cambia el correo del perfil hasta confirmarlo desde la direccion nueva', function () {
    $admin = User::factory()->role('admin')->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::actingAs($admin)->test(EditProfile::class)
        ->fillForm(['email' => 'nueva.direccion@correo.test', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->email)->not->toBe('nueva.direccion@correo.test');
    Notification::assertSentTo($admin, NoticeOfEmailChangeRequest::class);
    Notification::assertSentOnDemand(
        VerifyEmailChange::class,
        fn (VerifyEmailChange $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'nueva.direccion@correo.test',
    );
});

it('vence el codigo por correo con el plazo de RN-15', function () {
    expect(EmailCodeAuthentication::make()->getCodeExpiryMinutes())->toBe(10);

    DB::table('business_rule_settings')->insert([
        'rule_code' => 'RN-15', 'param_key' => 'otp_ttl_minutes', 'param_value' => '15', 'value_type' => 'int',
        'effective_from' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(EmailCodeAuthentication::make()->getCodeExpiryMinutes())->toBe(15);
});
