<?php

declare(strict_types=1);

use App\Filament\Pages\ApprovalInbox;
use App\Filament\Pages\BusinessRules;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\AuditLogEntries\AuditLogEntryResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\ScheduleBlocks\Pages\CreateScheduleBlock;
use App\Filament\Resources\ScheduleBlocks\ScheduleBlockResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
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
});

it('abre cada pantalla del panel a la administradora', function () {
    $id = requestAppointment('2026-09-18 15:00');
    // Varias filas de historial: un N+1 en el detalle revienta con preventLazyLoading.
    app(AppointmentTransitions::class)->approve($id, $this->secretary->id);
    app(AppointmentTransitions::class)->payAtDesk($id);

    foreach ([
        ApprovalInbox::getUrl(), AppointmentResource::getUrl('index'), AppointmentResource::getUrl('view', ['record' => $id]),
        AppointmentResource::getUrl('create'), PaymentResource::getUrl('index'), ScheduleBlockResource::getUrl('index'),
        ServiceResource::getUrl('index'), BusinessRules::getUrl(), AuditLogEntryResource::getUrl('index'),
    ] as $url) {
        $this->actingAs($this->admin)->get($url)->assertSuccessful();
    }
});

it('niega a recepcion lo que es exclusivo de la psicologa (doc 05 §4.3)', function (string $url) {
    $this->actingAs($this->secretary)->get($url)->assertForbidden();
})->with([
    'bloqueos' => fn () => ScheduleBlockResource::getUrl('index'),
    'tarifas' => fn () => ServiceResource::getUrl('index'),
    'parametros' => fn () => BusinessRules::getUrl(),
    'bitacora' => fn () => AuditLogEntryResource::getUrl('index'),
]);

it('ordena la bandeja por el SLA que queda y aprueba desde ella (HU-04)', function () {
    // La solicitada primero vence primero, aunque su cita sea antes o despues.
    $expiresFirst = requestAppointment('2026-09-18 16:00');
    $this->travelTo(gtAt('2026-09-15 12:00'));
    $expiresLater = requestAppointment('2026-09-18 15:00', 'otra@correo.test');
    $this->travelTo(gtAt('2026-09-15 13:00'));

    Livewire::actingAs($this->secretary)
        ->test(ApprovalInbox::class)
        ->assertCanSeeTableRecords(Appointment::whereKey([$expiresFirst, $expiresLater])->get()->sortBy('hold_expires_at'), inOrder: true)
        ->callTableAction('approve', $expiresFirst);

    expect(statusOf($expiresFirst))->toBe('CONFIRMADA_PENDIENTE_PAGO');
});

it('aprueba en lote las solicitudes seleccionadas', function () {
    $ids = [requestAppointment('2026-09-18 15:00'), requestAppointment('2026-09-18 16:00', 'b@correo.test')];

    Livewire::actingAs($this->secretary)
        ->test(ApprovalInbox::class)
        ->callTableBulkAction('approveSelected', $ids);

    expect(array_map('statusOf', $ids))->each->toBe('CONFIRMADA_PENDIENTE_PAGO');
});

it('exige motivo para rechazar desde la bandeja', function () {
    $id = requestAppointment('2026-09-18 15:00');

    Livewire::actingAs($this->secretary)
        ->test(ApprovalInbox::class)
        ->callTableAction('reject', $id, data: ['reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    expect(statusOf($id))->toBe('SOLICITADA');
});

it('agenda por telefono el mismo dia, ya aprobada (T-11)', function () {
    $patient = User::factory()->role('patient')->create();
    Patient::create(['id' => $patient->id]);

    Livewire::actingAs($this->secretary)
        ->test(CreateAppointment::class)
        ->fillForm([
            'patient_id' => $patient->id,
            'service_id' => serviceId(),
            'date' => '2026-09-15',
            'starts_at' => gtAt('2026-09-15 17:00')->getTimestamp(),
            'source' => 'phone',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $appointment = Appointment::sole();

    expect($appointment->source)->toBe('phone')
        ->and(statusOf($appointment->id))->toBe('CONFIRMADA_PENDIENTE_PAGO');
});

it('rechaza un bloqueo con citas sin resolver y lo acepta despues (RN-18)', function () {
    $id = requestAppointment('2026-09-18 15:00');
    $pending = requestAppointment('2026-09-18 17:00', 'otra@correo.test');
    app(AppointmentTransitions::class)->approve($id, $this->admin->id);

    $block = ['starts_at' => '2026-09-18 08:00', 'ends_at' => '2026-09-18 19:00', 'reason' => 'Congreso'];

    Livewire::actingAs($this->admin)->test(CreateScheduleBlock::class)
        ->fillForm($block)->call('create')->assertHasFormErrors(['starts_at']);

    expect(DB::table('schedule_blocks')->count())->toBe(0);

    app(AppointmentTransitions::class)->cancelByTherapist($id, $this->admin->id);
    app(AppointmentTransitions::class)->cancelByTherapist($pending, $this->admin->id);

    Livewire::actingAs($this->admin)->test(CreateScheduleBlock::class)
        ->fillForm($block)->call('create')->assertHasNoFormErrors();

    expect(statusOf($id))->toBe('CANCELADA_SIN_CARGO')
        ->and(statusOf($pending))->toBe('RECHAZADA')
        ->and(dispatchedTemplates())->toContain('NT-22')
        ->and(fn () => requestAppointment('2026-09-18 16:00', 'x@correo.test'))->toThrow(SlotUnavailable::class);
});

it('lista la agenda semanal con 8 consultas o menos (CA-17)', function () {
    foreach (['15:00', '16:00', '17:00', '18:00'] as $i => $time) {
        requestAppointment("2026-09-18 {$time}", "p{$i}@correo.test");
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    AppointmentResource::getEloquentQuery()
        ->whereBetween('starts_at', [gtAt('2026-09-14 00:00'), gtAt('2026-09-21 00:00')])
        ->get()
        ->each(fn (Appointment $a) => [$a->patient->name, $a->service->name, $a->status->label]);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(8);
});
