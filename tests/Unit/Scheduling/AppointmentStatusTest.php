<?php

declare(strict_types=1);

use App\Scheduling\Domain\Model\AppointmentStatus as S;

// Las transiciones del diagrama de Entregable_1/04 mas las declaradas en
// docs/fase-2: cancelar desde pago pendiente y desde caja (mockup P-02).
const ALLOWED = [
    'SOLICITADA' => ['CONFIRMADA_PENDIENTE_PAGO', 'RECHAZADA', 'EXPIRADA'],
    'CONFIRMADA_PENDIENTE_PAGO' => ['PAGO_EN_REVISION', 'PAGO_EN_CAJA', 'CANCELADA_IMPAGO', 'CANCELADA_SIN_CARGO', 'CANCELADA_CON_RECARGO'],
    'PAGO_EN_REVISION' => ['CONFIRMADA_PENDIENTE_PAGO', 'CANCELADA_IMPAGO', 'AGENDADA'],
    'PAGO_EN_CAJA' => ['AGENDADA', 'CANCELADA_SIN_CARGO', 'CANCELADA_CON_RECARGO'],
    'AGENDADA' => ['EN_CURSO', 'CANCELADA_SIN_CARGO', 'CANCELADA_CON_RECARGO', 'NO_SHOW'],
    'EN_CURSO' => ['ATENDIDA'],
];

it('permite exactamente las transiciones del diagrama', function (S $from) {
    foreach (S::cases() as $to) {
        expect($from->canTransitionTo($to))
            ->toBe(in_array($to->value, ALLOWED[$from->value] ?? [], true), "{$from->value} -> {$to->value}");
    }
})->with(S::cases());

it('marca como terminales los siete estados sin salida', function () {
    $terminal = array_values(array_filter(S::cases(), fn (S $s) => $s->isTerminal()));

    expect(array_map(fn (S $s) => $s->value, $terminal))->toEqualCanonicalizing([
        'ATENDIDA', 'RECHAZADA', 'EXPIRADA', 'CANCELADA_IMPAGO', 'CANCELADA_SIN_CARGO', 'CANCELADA_CON_RECARGO', 'NO_SHOW',
    ]);
});

it('no libera el horario en una inasistencia', function () {
    expect(S::NO_SHOW->releasesSlot())->toBeFalse()
        ->and(S::EXPIRADA->releasesSlot())->toBeTrue()
        ->and(S::CANCELADA_CON_RECARGO->releasesSlot())->toBeTrue();
});
