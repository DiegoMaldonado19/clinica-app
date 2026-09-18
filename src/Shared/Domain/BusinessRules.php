<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Los parametros RN-xx vigentes. El dominio los recibe por aqui y nunca como
 * constantes (CLAUDE.md).
 */
interface BusinessRules
{
    /** @return int|float|bool|string|array<mixed> */
    public function value(string $ruleCode, string $paramKey): int|float|bool|string|array;
}
