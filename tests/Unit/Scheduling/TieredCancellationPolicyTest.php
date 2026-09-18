<?php

declare(strict_types=1);

use App\Scheduling\Domain\Policy\CancellationTier;
use App\Scheduling\Domain\Policy\TieredCancellationPolicy;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Shared\Domain\ValueObject\Money;

function rn06(): TieredCancellationPolicy
{
    return TieredCancellationPolicy::fromSetting([['h' => 24, 'pct' => 0], ['h' => 1, 'pct' => 50], ['h' => 0, 'pct' => 100]]);
}

// Los siete casos frontera del doc 11 §2.1 (CA-05).
it('aplica el tramo correcto de RN-06 en cada frontera', function (int $secondsBefore, int $percentage, string $reason) {
    $start = new DateTimeImmutable('2026-08-13 15:00:00');
    $slot = TimeSlot::starting($start, 50);

    $outcome = rn06()->evaluate($slot, Money::gtq(40000), $start->modify("-{$secondsBefore} seconds"));

    expect($outcome->feePercentage)->toBe($percentage)
        ->and($outcome->feeAmount->amountInCents)->toBe(40000 * $percentage / 100)
        ->and($outcome->feeApplies)->toBe($percentage > 0)
        ->and($outcome->reasonCode)->toBe($reason);
})->with([
    '24 h 00 min 01 s' => [24 * 3600 + 1, 0, 'no_fee'],
    'exactamente 24 h' => [24 * 3600, 0, 'no_fee'],
    '23 h 59 min' => [23 * 3600 + 59 * 60, 50, 'late_50'],
    '1 h 00 min 01 s' => [3600 + 1, 50, 'late_50'],
    'exactamente 1 h' => [3600, 50, 'late_50'],
    '59 min' => [59 * 60, 100, 'late_100'],
    'despues del inicio' => [-600, 100, 'late_100'],
]);

// La prueba que resume la arquitectura (doc 11 §2.2): sin base, sin framework.
it('aplica 50 % exactamente en el umbral de una hora', function () {
    $politica = new TieredCancellationPolicy([
        new CancellationTier(minHoursBefore: 24, feePercentage: 0),
        new CancellationTier(minHoursBefore: 1, feePercentage: 50),
        new CancellationTier(minHoursBefore: 0, feePercentage: 100),
    ]);

    $resultado = $politica->evaluate(
        TimeSlot::starting(new DateTimeImmutable('2026-08-13 15:00:00'), 50),
        Money::gtq(40000),
        new DateTimeImmutable('2026-08-13 14:00:00'),
    );

    expect($resultado->feeApplies)->toBeTrue()
        ->and($resultado->feeAmount->amountInCents)->toBe(20000)
        ->and($resultado->reasonCode)->toBe('late_50');
});

it('ordena los tramos aunque la configuracion llegue desordenada', function () {
    $policy = TieredCancellationPolicy::fromSetting([['h' => 0, 'pct' => 100], ['h' => 24, 'pct' => 0], ['h' => 1, 'pct' => 50]]);
    $slot = TimeSlot::starting(new DateTimeImmutable('2026-08-13 15:00:00'), 50);

    expect($policy->evaluate($slot, Money::gtq(30000), new DateTimeImmutable('2026-08-10 15:00:00'))->feePercentage)->toBe(0);
});
