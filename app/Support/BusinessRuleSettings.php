<?php

declare(strict_types=1);

namespace App\Support;

use App\Shared\Domain\BusinessRules;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lectura de los parametros RN-xx. Existe para que ninguna regla acabe escrita
 * como constante en el codigo (CLAUDE.md).
 *
 * Devuelve el valor vigente: la fila con `effective_from` mas reciente que ya
 * haya entrado en vigor, que es lo que permite programar un cambio con fecha.
 */
final class BusinessRuleSettings implements BusinessRules
{
    /** @return int|float|bool|string|array<mixed> */
    public function value(string $ruleCode, string $paramKey): int|float|bool|string|array
    {
        $row = DB::table('business_rule_settings')
            ->where('rule_code', $ruleCode)
            ->where('param_key', $paramKey)
            ->where('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->first(['param_value', 'value_type']);

        if ($row === null) {
            throw new RuntimeException("Parametro {$ruleCode}.{$paramKey} sin valor vigente.");
        }

        return match ($row->value_type) {
            'int' => (int) $row->param_value,
            'decimal' => (float) $row->param_value,
            'bool' => filter_var($row->param_value, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $row->param_value, true, flags: JSON_THROW_ON_ERROR),
            default => (string) $row->param_value,
        };
    }
}
