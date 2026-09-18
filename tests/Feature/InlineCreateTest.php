<?php

declare(strict_types=1);

use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->role('admin')->create();
    $this->secretary = User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));
});

function newPatient(): array
{
    return ['name' => 'Luis Gómez', 'email' => 'luis@correo.test', 'phone_e164' => '5555-4321', 'nit' => 'CF'];
}

it('da de alta al paciente desde el desplegable al agendar y lo deja elegido (MU-09)', function () {
    $page = Livewire::actingAs($this->secretary)
        ->test(CreateAppointment::class)
        ->callFormComponentAction('patient_id', 'createOption', data: newPatient())
        ->assertHasNoFormComponentActionErrors();

    $patient = User::where('email', 'luis@correo.test')->sole();

    $page->assertFormSet(['patient_id' => $patient->id]);

    expect($patient->phone_e164)->toBe('+50255554321')
        ->and($patient->must_change_password)->toBeTrue()
        ->and(DB::table('patients')->where('id', $patient->id)->exists())->toBeTrue()
        ->and(dispatchedTemplates($patient->id))->toBe(['NT-02']);
});

it('termina el agendamiento con el paciente recien creado', function () {
    Livewire::actingAs($this->secretary)
        ->test(CreateAppointment::class)
        ->callFormComponentAction('patient_id', 'createOption', data: newPatient())
        ->fillForm([
            'service_id' => serviceId(),
            'date' => '2026-09-15',
            'starts_at' => gtAt('2026-09-15 17:00')->getTimestamp(),
            'source' => 'phone',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DB::table('appointments')->value('patient_id'))->toBe(User::where('email', 'luis@correo.test')->value('id'));
});

it('aplica las mismas validaciones que la pantalla completa', function () {
    User::factory()->role('patient')->create(['email' => 'luis@correo.test']);

    Livewire::actingAs($this->secretary)
        ->test(CreateAppointment::class)
        ->callFormComponentAction('patient_id', 'createOption', data: ['phone_e164' => '12', 'nit' => '1234567-8'] + newPatient())
        ->assertHasFormComponentActionErrors(['email', 'phone_e164', 'nit']);

    expect(User::where('email', 'luis@correo.test')->count())->toBe(1);
});

it('deja crear un servicio desde el desplegable solo a quien administra tarifas', function () {
    Livewire::actingAs($this->secretary)
        ->test(CreateAppointment::class)
        ->assertFormComponentActionHidden('service_id', 'createOption');

    Livewire::actingAs($this->admin)
        ->test(CreateAppointment::class)
        ->callFormComponentAction('service_id', 'createOption', data: [
            'name' => 'Terapia grupal', 'category_id' => ServiceCategory::value('id'),
            'duration_minutes' => 60, 'initial_price' => 200, 'is_active' => true,
        ])
        ->assertHasNoFormComponentActionErrors()
        ->assertFormSet(['service_id' => Service::where('name', 'Terapia grupal')->value('id')]);

    expect(Service::where('name', 'Terapia grupal')->sole()->currentPrice()?->amountInCents)->toBe(20000);
});

it('crea la cuenta del paciente desde su ficha, sin pasar por Usuarios', function () {
    $page = Livewire::actingAs($this->secretary)
        ->test(CreatePatient::class)
        ->callFormComponentAction('id', 'createOption', data: ['name' => 'Sara Ruiz', 'email' => 'sara@correo.test', 'phone_e164' => '5555-0000'])
        ->assertHasNoFormComponentActionErrors();

    $account = User::where('email', 'sara@correo.test')->sole();

    $page->assertFormSet(['id' => $account->id])
        ->fillForm(['nit' => 'CF'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DB::table('patients')->where('id', $account->id)->value('nit'))->toBe('CF')
        ->and($account->role->code)->toBe('patient');
});

it('crea una categoria desde el desplegable del servicio', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateService::class)
        ->callFormComponentAction('category_id', 'createOption', data: ['label' => 'Terapia grupal'])
        ->assertHasNoFormComponentActionErrors()
        ->assertFormSet(['category_id' => ServiceCategory::where('code', 'TERAPIA_GRUPAL')->value('id')]);
});
