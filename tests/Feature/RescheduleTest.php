<?php

declare(strict_types=1);

use App\Filament\Portal\Pages\MyAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
use App\Scheduling\Application\BookingService;
use App\Scheduling\Domain\Exception\SameDayBookingNotAllowed;
use App\Scheduling\Domain\Exception\SlotUnavailable;
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

    $this->id = requestAppointment('2026-09-18 15:00');
    app(AppointmentTransitions::class)->approve($this->id, $this->secretary->id);
    payAndBook($this->id, $this->secretary->id);
    $this->patient = User::where('email', 'ana.perez@correo.test')->sole();
});

function rescheduleTo(string $id, string $startsAt, string $by, bool $byTherapist = false, bool $assisted = true): string
{
    return DB::transaction(fn () => app(BookingService::class)->reschedule($id, gtAt($startsAt)->toDateTimeImmutable(), $by, $byTherapist, $assisted));
}

function sessionDue(string $appointmentId): int
{
    return (int) DB::table('payments')->where('appointment_id', $appointmentId)->where('kind', 'SESSION_FEE')->value('amount_cents');
}

it('reprograma en una transaccion: lo pagado pasa como credito a la cita nueva (RF-28, RN-16)', function () {
    $newId = rescheduleTo($this->id, '2026-09-21 09:00', $this->patient->id, assisted: false);

    expect(statusOf($this->id))->toBe('CANCELADA_SIN_CARGO')
        ->and(statusOf($newId))->toBe('AGENDADA')
        ->and(DB::table('appointments')->where('id', $newId)->value('rescheduled_from_id'))->toBe($this->id)
        ->and(sessionDue($newId))->toBe(0)
        ->and(DB::table('patient_credits')->whereNull('consumed_at')->count())->toBe(0)
        ->and(DB::table('patient_credits')->value('consumed_by_appointment_id'))->toBe($newId)
        ->and(array_count_values(dispatchedTemplates($this->patient->id)))->toMatchArray(['NT-04' => 1, 'NT-08' => 2, 'NT-13' => 1]);
});

it('cobra el tramo de RN-06 al reprogramar tarde y aplica el resto a la cita nueva', function () {
    $this->travelTo(gtAt('2026-09-18 09:00'));

    $newId = rescheduleTo($this->id, '2026-09-21 09:00', $this->secretary->id);

    expect(statusOf($this->id))->toBe('CANCELADA_CON_RECARGO')
        ->and(statusOf($newId))->toBe('CONFIRMADA_PENDIENTE_PAGO')
        ->and(sessionDue($newId))->toBe(15000);
});

it('no cobra si reprograma la psicologa por indisponibilidad (RN-18)', function () {
    $this->travelTo(gtAt('2026-09-18 09:00'));

    $newId = rescheduleTo($this->id, '2026-09-21 09:00', $this->admin->id, byTherapist: true);

    expect(statusOf($this->id))->toBe('CANCELADA_SIN_CARGO')
        ->and(statusOf($newId))->toBe('AGENDADA')
        ->and(DB::table('payments')->where('kind', 'LATE_CANCELLATION_FEE')->count())->toBe(0);
});

it('deja la cita como estaba si el horario nuevo no se puede tomar', function () {
    requestAppointment('2026-09-21 09:00', 'otra@correo.test');

    expect(fn () => rescheduleTo($this->id, '2026-09-21 09:00', $this->secretary->id))->toThrow(SlotUnavailable::class)
        ->and(fn () => rescheduleTo($this->id, '2026-09-15 16:00', $this->patient->id, assisted: false))->toThrow(SameDayBookingNotAllowed::class)
        ->and(statusOf($this->id))->toBe('AGENDADA')
        ->and(DB::table('appointments')->count())->toBe(2);
});

it('deja reprogramar al paciente desde su portal', function () {
    $this->patient->forceFill(['must_change_password' => false])->save();

    Livewire::actingAs($this->patient)->test(MyAppointments::class)
        ->callTableAction('reschedule', $this->id, data: ['date' => '2026-09-21', 'starts_at' => gtAt('2026-09-21 09:00')->getTimestamp()])
        ->assertHasNoTableActionErrors();

    expect(statusOf($this->id))->toBe('CANCELADA_SIN_CARGO')
        ->and(DB::table('appointments')->where('rescheduled_from_id', $this->id)->exists())->toBeTrue();
});

it('da a recepcion reprogramar a pedido del paciente y solo a la psicologa por indisponibilidad', function () {
    Livewire::actingAs($this->secretary)->test(ViewAppointment::class, ['record' => $this->id])
        ->assertActionVisible('reschedule')
        ->assertActionHidden('rescheduleByTherapist');

    Livewire::actingAs($this->admin)->test(ViewAppointment::class, ['record' => $this->id])
        ->callAction('rescheduleByTherapist', data: ['date' => '2026-09-21', 'starts_at' => gtAt('2026-09-21 09:00')->getTimestamp()]);

    expect(statusOf($this->id))->toBe('CANCELADA_SIN_CARGO');
});

it('aplica el credito a la siguiente cita aprobada y lo devuelve si esa se cancela por impago', function () {
    $this->travelTo(gtAt('2026-09-18 09:00'));
    app(AppointmentTransitions::class)->cancel($this->id, null);
    $next = requestAppointment('2026-09-22 09:00');
    app(AppointmentTransitions::class)->approve($next, $this->secretary->id);

    expect(sessionDue($next))->toBe(15000)
        ->and(DB::table('patient_credits')->whereNull('consumed_at')->sum('amount_cents'))->toEqual(0);

    $this->travelTo(gtAt('2026-09-22 06:01'));
    app(AppointmentTransitions::class)->cancelIfUnpaid($next);

    expect(statusOf($next))->toBe('CANCELADA_IMPAGO')
        ->and(DB::table('patient_credits')->whereNull('consumed_at')->sum('amount_cents'))->toEqual(15000);
});
