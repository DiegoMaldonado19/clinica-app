<?php

declare(strict_types=1);

use App\Billing\Application\BillingService;
use App\Billing\Domain\PaymentProof;
use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
use App\Scheduling\Infrastructure\Job\SendReminderJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->staff = User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));
    $this->transitions = app(AppointmentTransitions::class);
    $this->billing = app(BillingService::class);

    $this->id = requestAppointment('2026-09-18 15:00');
    $this->transitions->approve($this->id, $this->staff->id);
});

function payAndBook(string $appointmentId, string $staffId): void
{
    $billing = app(BillingService::class);
    $billing->submitProof($appointmentId, new PaymentProof(
        (string) random_int(1, 999999), (int) DB::table('banks')->value('id'), '4471',
        new DateTimeImmutable('2026-09-15'), 'comprobantes/x.pdf', str_repeat('b', 64), 1000,
    ));
    $billing->approve((string) DB::table('payments')->where('appointment_id', $appointmentId)->value('id'), $staffId);
}

function creditCents(): int
{
    return (int) DB::table('patient_credits')->sum('amount_cents');
}

it('cancela por impago en T-3 h aunque el comprobante siga en revision (CA-04)', function () {
    $this->billing->submitProof($this->id, new PaymentProof('9', (int) DB::table('banks')->value('id'), '0001', new DateTimeImmutable('2026-09-15'), 'x.pdf', str_repeat('c', 64), 1));

    $this->travelTo(gtAt('2026-09-18 12:01'));
    $this->artisan('appointments:reconcile')->assertSuccessful();

    expect(statusOf($this->id))->toBe('CANCELADA_IMPAGO')
        ->and(dispatchedTemplates())->toContain('NT-10');
});

it('no cancela por impago una cita que se paga en caja', function () {
    $this->transitions->payAtDesk($this->id);

    $this->travelTo(gtAt('2026-09-18 12:01'));
    $this->artisan('appointments:reconcile')->assertSuccessful();

    expect(statusOf($this->id))->toBe('PAGO_EN_CAJA');
});

it('no cancela por impago una cita ya agendada', function () {
    payAndBook($this->id, $this->staff->id);

    $this->travelTo(gtAt('2026-09-18 12:01'));

    expect($this->transitions->cancelIfUnpaid($this->id))->toBeFalse()
        ->and(statusOf($this->id))->toBe('AGENDADA');
});

it('cancela una cita pagada con 48 h sin cargo y deja todo como credito (RN-16)', function () {
    payAndBook($this->id, $this->staff->id);

    $this->travelTo(gtAt('2026-09-16 15:00'));
    $outcome = $this->transitions->cancel($this->id, null);

    expect($outcome->feeApplies)->toBeFalse()
        ->and(statusOf($this->id))->toBe('CANCELADA_SIN_CARGO')
        ->and(creditCents())->toBe(30000)
        ->and(dispatchedTemplates())->toContain('NT-13', 'NT-23');
});

it('cobra el 50 % de una cita pagada sin cobrarla dos veces', function () {
    payAndBook($this->id, $this->staff->id);

    $this->travelTo(gtAt('2026-09-18 09:00'));
    $outcome = $this->transitions->cancel($this->id, null);

    $fee = DB::table('payments')->where('appointment_id', $this->id)->where('kind', 'LATE_CANCELLATION_FEE')->first();

    expect($outcome->feeAmount->amountInCents)->toBe(15000)
        ->and(statusOf($this->id))->toBe('CANCELADA_CON_RECARGO')
        ->and((int) $fee->amount_cents)->toBe(15000)
        ->and($fee->status_id)->toBe(DB::table('payment_statuses')->where('code', 'APROBADO')->value('id'))
        ->and(creditCents())->toBe(15000)
        ->and(dispatchedTemplates())->toContain('NT-14');
});

it('deja pendiente el cargo de una cita que no se habia pagado', function () {
    $this->travelTo(gtAt('2026-09-18 14:30'));
    $this->transitions->cancel($this->id, null);

    expect(DB::table('payments')->where('kind', 'LATE_CANCELLATION_FEE')->value('status_id'))
        ->toBe(DB::table('payment_statuses')->where('code', 'PENDIENTE')->value('id'))
        ->and(creditCents())->toBe(0);
});

it('exonera un cargo cubierto devolviendolo como credito (RF-26)', function () {
    payAndBook($this->id, $this->staff->id);
    $this->travelTo(gtAt('2026-09-18 14:30'));
    $this->transitions->cancel($this->id, null);
    $feeId = DB::table('payments')->where('kind', 'LATE_CANCELLATION_FEE')->value('id');

    $this->billing->waive($feeId, $this->staff->id, 'Emergencia médica documentada.');

    expect(creditCents())->toBe(30000)
        ->and(DB::table('payments')->where('id', $feeId)->value('status_id'))
        ->toBe(DB::table('payment_statuses')->where('code', 'EXONERADO')->value('id'));
});

it('marca inasistencia con 100 % sin liberar el horario (CA-06)', function () {
    payAndBook($this->id, $this->staff->id);
    $this->travelTo(gtAt('2026-09-18 15:30'));

    $outcome = $this->transitions->markNoShow($this->id, $this->staff->id);

    expect($outcome->feePercentage)->toBe(100)
        ->and(statusOf($this->id))->toBe('NO_SHOW')
        ->and(DB::table('appointments')->where('id', $this->id)->value('active_slot'))->toBe(1)
        ->and(creditCents())->toBe(0);
});

it('no envia el recordatorio de una cita cancelada (HU-10)', function () {
    Queue::fake();
    $other = requestAppointment('2026-09-18 16:00', 'otra@correo.test');
    $this->transitions->approve($other, $this->staff->id);
    $this->travelTo(gtAt('2026-09-16 10:00'));
    $this->transitions->cancel($other, null);

    app()->call([new SendReminderJob($other, 24), 'handle']);

    expect(dispatchedTemplates(DB::table('appointments')->where('id', $other)->value('patient_id')))->not->toContain('NT-11');
});

it('programa los recordatorios de T-24 h y T-2 h al aprobar (RN-08)', function () {
    Queue::fake();
    $other = requestAppointment('2026-09-18 16:00', 'otra@correo.test');
    $this->transitions->approve($other, $this->staff->id);

    Queue::assertPushed(SendReminderJob::class, 2);
});
