<?php

declare(strict_types=1);

use App\Scheduling\Domain\Event\AppointmentApproved;
use App\Scheduling\Domain\Event\PreAppointmentRequested;
use App\Scheduling\Domain\Exception\BookingTooSoon;
use App\Scheduling\Domain\Exception\InvalidStateTransition;
use App\Scheduling\Domain\Exception\SameDayBookingNotAllowed;
use App\Scheduling\Domain\Model\Appointment;
use App\Scheduling\Domain\Model\AppointmentStatus;
use App\Scheduling\Domain\Policy\ApprovalSlaPolicy;
use App\Scheduling\Domain\Policy\BookingWindowPolicy;
use App\Scheduling\Domain\Policy\TieredCancellationPolicy;
use App\Scheduling\Domain\ValueObject\BookingSource;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Clock\FrozenClock;

// Fechas en hora de Guatemala (UTC-6) para que "el mismo dia" sea evidente.
function gt(string $local): DateTimeImmutable
{
    return new DateTimeImmutable($local, new DateTimeZone('America/Guatemala'));
}

function requestAt(string $now, string $start): Appointment
{
    $tz = new DateTimeZone('America/Guatemala');

    return Appointment::request(
        'a-1', 'p-1', 't-1', 's-1',
        TimeSlot::starting(gt($start), 50),
        Money::gtq(30000),
        BookingSource::WEB,
        new BookingWindowPolicy(false, 24, $tz),
        new ApprovalSlaPolicy(24, 72, $tz),
        new FrozenClock(gt($now)),
    );
}

it('nace SOLICITADA con el HOLD hasta el SLA de dia habil', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 09:00'); // martes

    expect($appointment->status())->toBe(AppointmentStatus::SOLICITADA)
        ->and($appointment->holdExpiresAt())->toEqual(gt('2026-09-16 10:00'))
        ->and($appointment->releaseEvents()[0])->toBeInstanceOf(PreAppointmentRequested::class);
});

it('da 72 horas a la solicitud hecha en fin de semana', function () {
    $appointment = requestAt('2026-09-19 09:00', '2026-09-25 09:00'); // sabado

    expect($appointment->holdExpiresAt())->toEqual(gt('2026-09-22 09:00'));
});

it('nunca deja el plazo de aprobacion despues del inicio de la cita', function () {
    $appointment = requestAt('2026-09-19 09:00', '2026-09-21 10:00'); // sabado -> lunes

    expect($appointment->holdExpiresAt())->toEqual(gt('2026-09-21 10:00'));
});

it('rechaza agendar para hoy por la web (RN-01)', function () {
    requestAt('2026-09-15 08:00', '2026-09-15 17:00');
})->throws(SameDayBookingNotAllowed::class);

it('rechaza agendar con menos de 24 horas (RN-02)', function () {
    requestAt('2026-09-15 18:00', '2026-09-16 09:00');
})->throws(BookingTooSoon::class);

it('fija el plazo de pago en T-3 h al aprobar', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 09:00');
    $appointment->approve('u-1', 3, new FrozenClock(gt('2026-09-15 11:00')));

    expect($appointment->status())->toBe(AppointmentStatus::CONFIRMADA_PENDIENTE_PAGO)
        ->and($appointment->paymentDueAt())->toEqual(gt('2026-09-18 06:00'))
        ->and($appointment->holdHasExpired(gt('2026-09-20 00:00')))->toBeFalse()
        ->and(array_values(array_filter($appointment->releaseEvents(), fn ($e) => $e instanceof AppointmentApproved)))->toHaveCount(1);
});

it('solo expira una solicitud cuyo plazo ya vencio', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 09:00');

    expect($appointment->holdHasExpired(gt('2026-09-16 09:59')))->toBeFalse()
        ->and($appointment->holdHasExpired(gt('2026-09-16 10:00')))->toBeTrue();

    $appointment->expire(new FrozenClock(gt('2026-09-16 10:01')));

    expect($appointment->status())->toBe(AppointmentStatus::EXPIRADA)
        ->and($appointment->status()->releasesSlot())->toBeTrue();
});

it('cancela por impago aunque el comprobante siga en revision (RN-05)', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 15:00');
    $appointment->approve('u-1', 3, new FrozenClock(gt('2026-09-15 11:00')));
    $appointment->submitPayment(new FrozenClock(gt('2026-09-16 11:00')));

    expect($appointment->paymentIsOverdue(gt('2026-09-18 12:01')))->toBeTrue();

    $appointment->cancelUnpaid(new FrozenClock(gt('2026-09-18 12:01')));

    expect($appointment->status())->toBe(AppointmentStatus::CANCELADA_IMPAGO);
});

it('no vence por impago una cita que se paga en caja', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 15:00');
    $appointment->approve('u-1', 3, new FrozenClock(gt('2026-09-15 11:00')));
    $appointment->payAtDesk(new FrozenClock(gt('2026-09-16 11:00')));

    expect($appointment->paymentIsOverdue(gt('2026-09-18 14:00')))->toBeFalse();
});

it('marca inasistencia con 100 % sin liberar el horario', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 15:00');
    $appointment->approve('u-1', 3, new FrozenClock(gt('2026-09-15 11:00')));
    $appointment->submitPayment(new FrozenClock(gt('2026-09-15 12:00')));
    $appointment->confirmPayment(new FrozenClock(gt('2026-09-15 13:00')));

    $outcome = $appointment->markNoShow('u-1', new FrozenClock(gt('2026-09-18 15:20')));

    expect($appointment->status())->toBe(AppointmentStatus::NO_SHOW)
        ->and($outcome->feePercentage)->toBe(100)
        ->and($outcome->feeAmount->amountInCents)->toBe(30000)
        ->and($appointment->status()->releasesSlot())->toBeFalse();
});

it('cancela con el tramo de RN-06 y no desde revision de pago', function () {
    $policy = TieredCancellationPolicy::fromSetting([['h' => 24, 'pct' => 0], ['h' => 1, 'pct' => 50], ['h' => 0, 'pct' => 100]]);
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 15:00');
    $appointment->approve('u-1', 3, new FrozenClock(gt('2026-09-15 11:00')));

    $outcome = $appointment->cancel($policy, new FrozenClock(gt('2026-09-18 09:00')));

    expect($appointment->status())->toBe(AppointmentStatus::CANCELADA_CON_RECARGO)
        ->and($outcome->feeAmount->amountInCents)->toBe(15000);

    $inReview = requestAt('2026-09-15 10:00', '2026-09-18 15:00');
    $inReview->approve('u-1', 3, new FrozenClock(gt('2026-09-15 11:00')));
    $inReview->submitPayment(new FrozenClock(gt('2026-09-15 12:00')));

    expect(fn () => $inReview->cancel($policy, new FrozenClock(gt('2026-09-16 09:00'))))->toThrow(DomainException::class);
});

it('no permite saltarse la maquina de estados', function () {
    $appointment = requestAt('2026-09-15 10:00', '2026-09-18 15:00');

    $appointment->confirmPayment(new FrozenClock(gt('2026-09-15 11:00')));
})->throws(InvalidStateTransition::class);

it('nace aprobada y en caja si recepcion agenda despues de T-3 h', function () {
    $appointment = Appointment::bookAssisted(
        'a-2', 'p-1', 't-1', 's-1', TimeSlot::starting(gt('2026-09-15 15:00'), 50), Money::gtq(30000),
        BookingSource::WALK_IN, 'u-2', 3, new FrozenClock(gt('2026-09-15 13:00')),
    );

    expect($appointment->status())->toBe(AppointmentStatus::PAGO_EN_CAJA);
});
