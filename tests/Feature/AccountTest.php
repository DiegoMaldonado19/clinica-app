<?php

declare(strict_types=1);

use App\Filament\Auth\EditProfile;
use App\Filament\Pages\BusinessRules;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// F3-10: contraseñas temporales, roles del personal (ADR-040) y fotos de perfil (ADR-041).
beforeEach(function () {
    Storage::fake('s3');
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->role('admin')->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function audited(string $action): bool
{
    return DB::table('audit_log')->where('action', $action)->exists();
}

it('genera la contraseña temporal con el boton junto al campo', function () {
    $page = Livewire::actingAs($this->admin)->test(CreateUser::class)
        ->callFormComponentAction('password', 'generate');

    expect($page->get('data.password'))->toHaveLength(12);
});

it('llama cambio de contraseña al campo de quien ya cambio la suya, y la vuelve temporal al guardar', function () {
    $user = User::factory()->role('secretary')->create(['must_change_password' => false]);

    Livewire::actingAs($this->admin)->test(EditUser::class, ['record' => $user->getKey()])
        ->assertSee('Cambio de contraseña')
        ->fillForm(['password' => 'Otra-Contrasena-Larga-7'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->must_change_password)->toBeTrue()
        ->and($user->temp_password_expires_at)->not->toBeNull()
        ->and(audited('user.password_reset'))->toBeTrue();
});

it('asigna una contraseña temporal y desactiva desde el listado, nunca sobre la propia cuenta', function () {
    $user = User::factory()->role('secretary')->create(['must_change_password' => false]);

    Livewire::actingAs($this->admin)->test(ListUsers::class)
        ->assertTableActionHidden('password', $this->admin)
        ->assertTableActionHidden('toggleActive', $this->admin)
        ->callTableAction('password', $user, data: ['password' => 'Otra-Contrasena-Larga-7'])
        ->assertHasNoTableActionErrors()
        ->callTableAction('toggleActive', $user);

    expect($user->refresh()->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeFalse()
        ->and(audited('user.password_reset'))->toBeTrue()
        ->and(audited('user.deactivated'))->toBeTrue();
});

it('no ofrece el rol Paciente en el alta de usuarios', function () {
    Livewire::actingAs($this->admin)->test(CreateUser::class)
        ->assertFormFieldExists('role_id', fn (Select $field): bool => ! in_array('Paciente', $field->getOptions(), true));
});

it('crea un rol del personal con sus permisos desde el desplegable, y ese rol entra al panel', function () {
    $checkIn = DB::table('abilities')->where('code', 'appointment.checkin')->value('id');
    $view = DB::table('abilities')->where('code', 'appointment.view.any')->value('id');

    Livewire::actingAs($this->admin)->test(CreateUser::class)
        ->callFormComponentAction('role_id', 'createOption', data: ['label' => 'Asistente clínica', 'abilities' => [$checkIn, $view]])
        ->assertHasNoFormComponentActionErrors()
        ->assertFormSet(['role_id' => Role::where('code', 'asistente_clinica')->value('id')]);

    $assistant = User::factory()->create(['role_id' => Role::where('code', 'asistente_clinica')->value('id')]);

    expect($assistant->hasAbility('appointment.checkin'))->toBeTrue()
        ->and($assistant->hasAbility('clinical_note.view'))->toBeFalse()
        ->and($assistant->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($assistant->canAccessPanel(Filament::getPanel('portal')))->toBeFalse()
        ->and(audited('role.created'))->toBeTrue();

    // Los permisos sembrados se reescriben en cada despliegue; los de este rol se quedan.
    $this->seed(DatabaseSeeder::class);

    expect(DB::table('role_ability')->where('role_id', $assistant->role_id)->count())->toBe(2);
});

it('rechaza un rol con el nombre o el codigo de uno que ya existe', function () {
    Livewire::actingAs($this->admin)->test(CreateUser::class)
        ->callFormComponentAction('role_id', 'createOption', data: ['label' => 'Patient', 'abilities' => [1]])
        ->assertHasFormComponentActionErrors(['label']);
});

it('guarda la foto del perfil en S3 y la muestra solo a su dueño y al personal', function () {
    $patient = User::factory()->role('patient')->create();
    $other = User::factory()->role('patient')->create();

    Livewire::actingAs($this->admin)->test(EditProfile::class)
        ->fillForm(['avatar_path' => UploadedFile::fake()->image('yo.jpg', 600, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = $this->admin->refresh()->avatar_path;
    $patient->forceFill(['avatar_path' => 'avatars/paciente.jpg'])->save();
    Storage::disk('s3')->put('avatars/paciente.jpg', 'foto');

    expect($path)->toStartWith('avatars/')
        ->and(Storage::disk('s3')->exists($path))->toBeTrue();

    $this->actingAs($this->admin)->get(route('avatar', $patient))->assertSuccessful();
    $this->actingAs($patient)->get(route('avatar', $patient))->assertSuccessful();
    $this->actingAs($other)->get(route('avatar', $patient))->assertNotFound();
});

it('no sirve un archivo fuera de avatars/ aunque la ruta guardada se altere', function () {
    Storage::disk('s3')->put('comprobantes/ajeno.pdf', 'pdf');
    $this->admin->forceFill(['avatar_path' => 'comprobantes/ajeno.pdf'])->save();

    $this->actingAs($this->admin)->get(route('avatar', $this->admin))->assertNotFound();
});

it('borra del bucket la foto reemplazada', function () {
    Storage::disk('s3')->put('avatars/vieja.jpg', 'foto');
    $this->admin->forceFill(['avatar_path' => 'avatars/vieja.jpg'])->save();

    $this->admin->update(['avatar_path' => 'avatars/nueva.jpg']);

    expect(Storage::disk('s3')->exists('avatars/vieja.jpg'))->toBeFalse();
});

it('programa un valor nuevo desde el menu de la regla (MU-15)', function () {
    Livewire::actingAs($this->admin)->test(BusinessRules::class)
        ->callTableAction('schedule', 'RN-02|min_booking_notice_hours', data: ['value' => '12', 'effective_from' => now()->addDay()])
        ->assertHasNoTableActionErrors();

    expect(DB::table('business_rule_settings')->where('rule_code', 'RN-02')->where('param_value', '12')->exists())->toBeTrue()
        ->and(audited('settings.changed'))->toBeTrue();
});
