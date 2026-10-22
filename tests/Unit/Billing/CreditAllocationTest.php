<?php

declare(strict_types=1);

use App\Billing\Domain\CreditAllocation;
use App\Shared\Domain\ValueObject\Money;

it('cubre toda la tarifa y deja a favor lo que sobra del credito (RN-16)', function () {
    $allocation = CreditAllocation::of(Money::gtq(30000), ['credito' => 50000]);

    expect($allocation->due->isZero())->toBeTrue()
        ->and($allocation->consumed)->toBe(['credito' => 30000]);
});

it('baja el monto pendiente cuando el credito no alcanza', function () {
    $allocation = CreditAllocation::of(Money::gtq(30000), ['credito' => 15000]);

    expect($allocation->due->amountInCents)->toBe(15000)
        ->and($allocation->consumed)->toBe(['credito' => 15000]);
});

it('usa primero el credito mas antiguo y no toca el que no hace falta', function () {
    $allocation = CreditAllocation::of(Money::gtq(30000), ['viejo' => 10000, 'medio' => 25000, 'nuevo' => 5000]);

    expect($allocation->consumed)->toBe(['viejo' => 10000, 'medio' => 20000])
        ->and($allocation->due->isZero())->toBeTrue();
});

it('sin credito se paga la tarifa completa', function () {
    $allocation = CreditAllocation::of(Money::gtq(30000), []);

    expect($allocation->due->amountInCents)->toBe(30000)
        ->and($allocation->consumed)->toBe([]);
});
