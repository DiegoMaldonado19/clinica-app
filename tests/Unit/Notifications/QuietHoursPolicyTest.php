<?php

declare(strict_types=1);

use App\Notifications\Domain\IdempotencyKey;
use App\Notifications\Domain\QuietHoursPolicy;

function quietHours(): QuietHoursPolicy
{
    return QuietHoursPolicy::fromSetting('21:00-07:00', new DateTimeZone('America/Guatemala'));
}

function gtTime(string $local): DateTimeImmutable
{
    return new DateTimeImmutable($local, new DateTimeZone('America/Guatemala'));
}

it('difiere a las 07:00 lo que cae en la ventana de silencio (RN-17)', function (string $at, string $expected) {
    expect(quietHours()->nextAllowed(gtTime($at)))->toEqual(gtTime($expected));
})->with([
    'noche' => ['2026-09-15 22:30', '2026-09-16 07:00'],
    'madrugada' => ['2026-09-16 05:00', '2026-09-16 07:00'],
    'justo al abrir la ventana' => ['2026-09-15 21:00', '2026-09-16 07:00'],
]);

it('no toca lo que cae fuera de la ventana', function (string $at) {
    expect(quietHours()->nextAllowed(gtTime($at)))->toEqual(gtTime($at));
})->with(['2026-09-15 07:00', '2026-09-15 20:59', '2026-09-15 13:00']);

it('da la misma clave al mismo evento, destinatario y canal', function () {
    expect(IdempotencyKey::for('e-1', 'u-1', 'MAIL'))->toBe(IdempotencyKey::for('e-1', 'u-1', 'MAIL'))
        ->and(IdempotencyKey::for('e-1', 'u-1', 'MAIL'))->not->toBe(IdempotencyKey::for('e-1', 'u-2', 'MAIL'));
});
