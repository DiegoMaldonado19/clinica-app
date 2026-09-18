<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    private function __construct(public string $e164) {}

    /** Ocho digitos se asumen de Guatemala; con codigo de pais se respeta. */
    public static function fromGuatemalan(string $raw): self
    {
        $digits = preg_replace('/[\s\-()]/', '', trim($raw)) ?? '';

        if (preg_match('/^\d{8}$/', $digits) === 1) {
            return new self('+502'.$digits);
        }

        if (preg_match('/^\+\d{8,15}$/', $digits) === 1) {
            return new self($digits);
        }

        throw new InvalidArgumentException('Ingresa un número de 8 dígitos de Guatemala, o incluye el código de país.');
    }
}
