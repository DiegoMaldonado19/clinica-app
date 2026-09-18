<?php

declare(strict_types=1);

use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->staff = User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));
    $this->transitions = app(AppointmentTransitions::class);
});

it('aprueba, abre el cobro y fija el plazo de pago en T-3 h', function () {
    $id = requestAppointment('2026-09-18 15:00');

    $this->transitions->approve($id, $this->staff->id);

    expect(statusOf($id))->toBe('CONFIRMADA_PENDIENTE_PAGO')
        ->and(DB::table('appointments')->where('id', $id)->value('payment_due_at'))->toBe(gtAt('2026-09-18 12:00')->format('Y-m-d H:i:s'))
        ->and(DB::table('payments')->where('appointment_id', $id)->value('kind'))->toBe('SESSION_FEE')
        ->and(dispatchedTemplates())->toContain('NT-04');
});

it('no expira una cita que ya fue aprobada', function () {
    $id = requestAppointment('2026-09-18 15:00');
    $this->transitions->approve($id, $this->staff->id);

    $this->travelTo(gtAt('2026-09-16 11:00'));

    expect($this->transitions->expireIfDue($id))->toBeFalse()
        ->and(statusOf($id))->toBe('CONFIRMADA_PENDIENTE_PAGO');
});

it('expira una sola vez aunque el trabajo corra dos veces (CA-03)', function () {
    $id = requestAppointment('2026-09-18 15:00');
    $this->travelTo(gtAt('2026-09-16 10:01'));

    expect($this->transitions->expireIfDue($id))->toBeTrue()
        ->and($this->transitions->expireIfDue($id))->toBeFalse()
        ->and(statusOf($id))->toBe('EXPIRADA')
        ->and(array_count_values(dispatchedTemplates())['NT-06'])->toBe(1)
        ->and(DB::table('appointments')->where('id', $id)->value('active_slot'))->toBeNull();
});

it('rechaza con motivo obligatorio y libera el horario', function () {
    $id = requestAppointment('2026-09-18 15:00');

    expect(fn () => $this->transitions->reject($id, $this->staff->id, '  '))->toThrow(DomainException::class);

    $this->transitions->reject($id, $this->staff->id, 'La psicóloga está en congreso.');

    expect(statusOf($id))->toBe('RECHAZADA')
        ->and(dispatchedTemplates())->toContain('NT-05');
});

it('aplica un SLA nuevo a las citas nuevas y no a las existentes (CA-14)', function () {
    $before = requestAppointment('2026-09-18 15:00');

    DB::table('business_rule_settings')->insert([
        'rule_code' => 'RN-03', 'param_key' => 'approval_sla_hours', 'param_value' => '5',
        'value_type' => 'int', 'effective_from' => now()->subMinute(),
    ]);

    $after = requestAppointment('2026-09-18 16:00', 'otro@correo.test');

    expect(DB::table('appointments')->where('id', $before)->value('hold_expires_at'))->toBe(gtAt('2026-09-16 10:00')->format('Y-m-d H:i:s'))
        ->and(DB::table('appointments')->where('id', $after)->value('hold_expires_at'))->toBe(gtAt('2026-09-15 15:00')->format('Y-m-d H:i:s'));
});

it('registra cada transicion en la bitacora de estados', function () {
    $id = requestAppointment('2026-09-18 15:00');
    $this->transitions->approve($id, $this->staff->id);

    expect(DB::table('appointment_status_log')->where('appointment_id', $id)->count())->toBe(2)
        ->and(DB::table('appointment_status_log')->where('appointment_id', $id)->whereNotNull('changed_by')->value('changed_by'))->toBe($this->staff->id);
});
