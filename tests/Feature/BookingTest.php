<?php

declare(strict_types=1);

use App\Models\User;
use App\Scheduling\Domain\Exception\SameDayBookingNotAllowed;
use App\Scheduling\Domain\Exception\SlotUnavailable;
use App\Scheduling\Domain\Model\Appointment;
use App\Scheduling\Domain\Port\AppointmentRepository;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00')); // martes
});

it('crea la pre-cita, el paciente con credencial temporal y los consentimientos (HU-02)', function () {
    $id = requestAppointment('2026-09-18 09:00');

    $patient = User::where('email', 'ana.perez@correo.test')->sole();

    expect(statusOf($id))->toBe('SOLICITADA')
        ->and($patient->must_change_password)->toBeTrue()
        ->and($patient->temp_password_expires_at->equalTo(now()->addHours(72)))->toBeTrue()
        ->and(DB::table('consents')->where('patient_id', $patient->id)->pluck('document_code')->sort()->values()->all())
        ->toBe(['CANCELACION', 'PRIVACIDAD'])
        ->and(DB::table('appointments')->where('id', $id)->value('source'))->toBe('web')
        ->and(dispatchedTemplates())->toEqualCanonicalizing(['NT-02', 'NT-01', 'NT-03']);
});

it('reconoce al paciente que regresa por su correo, sin credenciales nuevas (HU-03)', function () {
    requestAppointment('2026-09-18 09:00');
    requestAppointment('2026-09-18 10:00');

    expect(User::where('email', 'ana.perez@correo.test')->count())->toBe(1)
        ->and(array_count_values(dispatchedTemplates())['NT-02'])->toBe(1);
});

it('da el horario a una sola de dos solicitudes simultaneas (RN-04)', function () {
    // La segunda llega mientras la primera tiene el candado.
    $lock = Cache::lock('slot:'.therapistId().':'.gtAt('2026-09-18 09:00')->getTimestamp(), 10);
    $lock->get();

    expect(fn () => requestAppointment('2026-09-18 09:00'))->toThrow(SlotUnavailable::class);

    $lock->release();
    requestAppointment('2026-09-18 09:00');

    expect(DB::table('appointments')->count())->toBe(1);
})->skip(
    fn (): bool => getenv('CACHE_STORE') === 'null',
    'Sin cache el candado no existe (ADR-008); la defensa que queda, el indice unico, se prueba abajo.',
);

it('rechaza limpiamente si el horario se ocupa entre la validacion y la insercion', function () {
    requestAppointment('2026-09-18 09:00');

    // Otra cita con el mismo horario que esquiva la validacion: la detiene el indice unico.
    $intruder = Appointment::bookAssisted(
        (string) Str::uuid7(), DB::table('patients')->value('id'), therapistId(), serviceId(),
        TimeSlot::starting(gtAt('2026-09-18 09:00')->toDateTimeImmutable(), 50), Money::gtq(30000),
        BookingSource::PHONE, (string) User::first()->id, 3, app(ClockInterface::class),
    );

    expect(fn () => app(AppointmentRepository::class)->save($intruder))->toThrow(SlotUnavailable::class)
        ->and(DB::table('appointments')->count())->toBe(1)
        ->and(DB::table('appointment_status_log')->where('appointment_id', $intruder->id)->count())->toBe(0);
});

it('no deja agendar para hoy desde la web (RN-01, MSG-01)', function () {
    requestAppointment('2026-09-15 16:00');
})->throws(SameDayBookingNotAllowed::class);

it('no deja inventar un horario fuera del calendario publicado', function () {
    requestAppointment('2026-09-18 13:00'); // hora de almuerzo
})->throws(SlotUnavailable::class);

it('libera el horario al vencer y deja volver a reservarlo', function () {
    $first = requestAppointment('2026-09-18 09:00');

    $this->travelTo(gtAt('2026-09-16 10:01'));
    $this->artisan('appointments:reconcile')->assertSuccessful();

    $second = requestAppointment('2026-09-18 09:00', 'luis@correo.test');

    expect(statusOf($first))->toBe('EXPIRADA')
        ->and(statusOf($second))->toBe('SOLICITADA');
});
