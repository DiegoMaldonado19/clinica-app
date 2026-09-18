<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * NIT de Guatemala con digito verificador modulo 11, o `CF` (consumidor final).
 */
final readonly class Nit
{
    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $value = strtoupper(str_replace(['-', ' '], '', trim($raw)));

        if ($value === 'CF') {
            return new self($value);
        }

        if (preg_match('/^(\d+)([\dK])$/', $value, $parts) !== 1) {
            throw new InvalidArgumentException('El NIT no pasó la verificación. Si no tienes NIT, escribe "CF".');
        }

        [, $body, $check] = $parts;
        $factor = strlen($body) + 1;
        $sum = 0;

        foreach (str_split($body) as $digit) {
            $sum += (int) $digit * $factor--;
        }

        $expected = (11 - $sum % 11) % 11;

        if ($check !== ($expected === 10 ? 'K' : (string) $expected)) {
            throw new InvalidArgumentException('El NIT no pasó la verificación. Si no tienes NIT, escribe "CF".');
        }

        return new self($value);
    }
}
