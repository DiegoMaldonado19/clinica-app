<?php

declare(strict_types=1);

use App\Billing\Application\BillingService;
use App\Billing\Domain\DuplicateReceipt;
use App\Billing\Domain\PaymentProof;
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
    $this->billing = app(BillingService::class);
    $this->transitions = app(AppointmentTransitions::class);

    $this->id = requestAppointment('2026-09-18 15:00');
    $this->transitions->approve($this->id, $this->staff->id);
});

function proof(string $receipt = '778899', string $bank = 'BI'): PaymentProof
{
    return new PaymentProof(
        $receipt, (int) DB::table('banks')->where('code', $bank)->value('id'), '4471',
        new DateTimeImmutable('2026-09-15'), 'comprobantes/x.pdf', str_repeat('a', 64), 800_000,
    );
}

it('pasa a revision al subir el comprobante y avisa a recepcion (HU-06)', function () {
    $this->billing->submitProof($this->id, proof());

    expect(statusOf($this->id))->toBe('PAGO_EN_REVISION')
        ->and(DB::table('payment_proofs')->value('origin_account_mask'))->toBe('****4471')
        ->and(dispatchedTemplates($this->staff->id))->toContain('NT-07');
});

it('rechaza la misma boleta del mismo banco (RN-14, MSG-13)', function () {
    $this->billing->submitProof($this->id, proof());

    $other = requestAppointment('2026-09-18 16:00', 'otra@correo.test');
    $this->transitions->approve($other, $this->staff->id);

    expect(fn () => DB::transaction(fn () => $this->billing->submitProof($other, proof())))->toThrow(DuplicateReceipt::class)
        ->and(statusOf($other))->toBe('CONFIRMADA_PENDIENTE_PAGO');
});

it('acepta el mismo numero de boleta en otro banco', function () {
    $this->billing->submitProof($this->id, proof('778899', 'BANRURAL'));

    expect(statusOf($this->id))->toBe('PAGO_EN_REVISION');
});

it('rechaza un deposito con fecha futura', function () {
    $future = new PaymentProof('1', (int) DB::table('banks')->value('id'), '1234', new DateTimeImmutable('2026-09-20'), 'x.pdf', str_repeat('a', 64), 1);

    $this->billing->submitProof($this->id, $future);
})->throws(DomainException::class);

it('agenda la cita al aprobar el pago y avisa al paciente (HU-07)', function () {
    $this->billing->submitProof($this->id, proof());
    $paymentId = DB::table('payments')->where('appointment_id', $this->id)->value('id');

    $this->billing->approve($paymentId, $this->staff->id);

    expect(statusOf($this->id))->toBe('AGENDADA')
        ->and(dispatchedTemplates())->toContain('NT-08');
});

it('regresa a pago pendiente al rechazar el comprobante con motivo', function () {
    $this->billing->submitProof($this->id, proof());
    $paymentId = DB::table('payments')->where('appointment_id', $this->id)->value('id');

    expect(fn () => $this->billing->reject($paymentId, $this->staff->id, ''))->toThrow(DomainException::class);

    $this->billing->reject($paymentId, $this->staff->id, 'El monto no coincide.');

    expect(statusOf($this->id))->toBe('CONFIRMADA_PENDIENTE_PAGO')
        ->and(dispatchedTemplates())->toContain('NT-09');

    // Y admite un comprobante nuevo.
    $this->billing->submitProof($this->id, proof('112233'));
    expect(statusOf($this->id))->toBe('PAGO_EN_REVISION');
});

it('cobra en caja al registrar la llegada', function () {
    $this->transitions->payAtDesk($this->id);

    expect(DB::table('payments')->where('appointment_id', $this->id)->value('status_id'))
        ->toBe(DB::table('payment_statuses')->where('code', 'EN_CAJA')->value('id'));

    $this->travelTo(gtAt('2026-09-18 14:50'));
    $this->transitions->checkIn($this->id, $this->staff->id);

    expect(statusOf($this->id))->toBe('EN_CURSO')
        ->and(DB::table('payments')->where('appointment_id', $this->id)->value('status_id'))
        ->toBe(DB::table('payment_statuses')->where('code', 'APROBADO')->value('id'));
});
