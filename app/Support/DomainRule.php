<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use InvalidArgumentException;

/**
 * Valida un campo con un objeto de valor del dominio (NIT, telefono): la regla
 * vive una sola vez y su mensaje es el mensaje que ve la persona.
 */
final class DomainRule
{
    /** @param callable(string): mixed $parse */
    public static function from(callable $parse): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($parse): void {
            try {
                $parse((string) $value);
            } catch (InvalidArgumentException $exception) {
                $fail($exception->getMessage());
            }
        };
    }
}
