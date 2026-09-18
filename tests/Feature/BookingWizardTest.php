<?php

declare(strict_types=1);

use App\Livewire\BookingWizard;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->travelTo(gtAt('2026-09-15 10:00'));
});

function wizardAtStep3(): Testable
{
    return Livewire::test(BookingWizard::class)
        ->call('chooseService', serviceId())
        ->call('chooseDay', '2026-09-18')
        ->call('chooseSlot', gtAt('2026-09-18 09:00')->getTimestamp())
        ->assertSet('step', 3);
}

it('recorre A-01 a A-04 y deja la solicitud registrada (CA-01)', function () {
    wizardAtStep3()
        ->set('name', 'Ana Pérez')
        ->set('email', 'ana@correo.test')
        ->set('phone', '5555-1234')
        ->set('nit', 'CF')
        ->set('accepted', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('step', 4)
        ->assertSee('Recibimos tu solicitud');

    expect(DB::table('appointments')->count())->toBe(1)
        ->and(DB::table('users')->where('email', 'ana@correo.test')->value('phone_e164'))->toBe('+50255551234');
});

it('exige aceptar la politica y valida NIT y telefono con los mensajes del catalogo', function () {
    wizardAtStep3()
        ->set('name', '')
        ->set('email', 'no-es-correo')
        ->set('phone', '12')
        ->set('nit', '1234567-8')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'phone', 'nit', 'accepted'])
        ->assertSee('Debes aceptar la política de cancelación para continuar.')
        ->assertSee('El NIT no pasó la verificación.');

    expect(DB::table('appointments')->count())->toBe(0);
});

it('ofrece el telefono cuando se elige el dia de hoy (MSG-01)', function () {
    Livewire::test(BookingWizard::class)
        ->call('chooseService', serviceId())
        ->call('chooseDay', '2026-09-15')
        ->assertSet('day', null)
        ->assertSee('Las citas para el mismo día se coordinan por teléfono');
});

it('conserva los datos y ofrece horarios cercanos si el horario se ocupa (MSG-06)', function () {
    $wizard = wizardAtStep3()
        ->set('name', 'Luis Gómez')
        ->set('email', 'luis@correo.test')
        ->set('phone', '5555-9999')
        ->set('nit', 'CF')
        ->set('accepted', true);

    requestAppointment('2026-09-18 09:00'); // alguien mas lo toma mientras tanto

    $wizard->call('submit')
        ->assertSet('step', 2)
        ->assertSet('email', 'luis@correo.test')
        ->assertSee('Ese horario se acaba de ocupar')
        ->assertSee('Los horarios más cercanos');
});

it('marca como no disponibles los domingos y muestra solo horarios con 24 h de anticipacion', function () {
    $wizard = Livewire::test(BookingWizard::class)->call('chooseService', serviceId());
    $days = collect($wizard->get('calendar'))->flatten(1)->filter()->keyBy('date');

    expect($days['2026-09-20']['state'])->toBe('unavailable') // domingo
        ->and($days['2026-09-15']['state'])->toBe('unavailable') // hoy
        ->and($days['2026-09-18']['state'])->toBe('available');

    // Miercoles 16: solo quedan los de las 10:00 en adelante (24 h desde martes 10:00).
    $wizard->call('chooseDay', '2026-09-16');
    $first = $wizard->instance()->times[0]->startsAt;

    expect($first->getTimestamp())->toBe(gtAt('2026-09-16 10:00')->getTimestamp());
});

it('muestra en pantalla los horarios del dia elegido', function () {
    Livewire::test(BookingWizard::class)
        ->call('chooseService', serviceId())
        ->call('chooseDay', '2026-09-18')
        ->assertDontSee('No quedan horarios este día')
        ->assertSeeInOrder(['08:00', '09:00', '15:00', '18:00']);
});
