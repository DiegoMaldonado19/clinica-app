<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Nit;
use App\Shared\Domain\ValueObject\PhoneNumber;

it('acepta un NIT con verificador valido y CF', function (string $raw, string $normalized) {
    expect(Nit::from($raw)->value)->toBe($normalized);
})->with([
    ['1234567-9', '12345679'],
    ['cf', 'CF'],
    ['576937-K', '576937K'],
]);

it('rechaza un NIT con verificador invalido', function (string $raw) {
    Nit::from($raw);
})->throws(InvalidArgumentException::class)->with(['1234567-8', 'ABC', '']);

it('normaliza telefonos de Guatemala a E.164', function (string $raw, string $e164) {
    expect(PhoneNumber::fromGuatemalan($raw)->e164)->toBe($e164);
})->with([
    ['5555-1234', '+50255551234'],
    ['+1 555 123 4567', '+15551234567'],
]);

it('rechaza un telefono incompleto', function () {
    PhoneNumber::fromGuatemalan('5555');
})->throws(InvalidArgumentException::class);

it('calcula porcentajes en centavos enteros y formatea en quetzales', function () {
    expect(Money::gtq(30000)->percentage(50)->amountInCents)->toBe(15000)
        ->and(Money::gtq(33333)->percentage(50)->amountInCents)->toBe(16666)
        ->and(Money::gtq(30000)->format())->toBe('Q 300')
        ->and(Money::gtq(30050)->format())->toBe('Q 300.50')
        ->and(Money::gtq(30000)->subtract(Money::gtq(15000))->amountInCents)->toBe(15000);
});
