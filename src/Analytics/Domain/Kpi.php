<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

use App\Shared\Domain\ValueObject\Money;

/** Un indicador del doc 05 de la Fase 1, o una cifra de resumen sin código. */
final readonly class Kpi
{
    /** @param int|float|null $value porcentaje, horas, centavos o conteo según `unit`; null es «sin dato» */
    public function __construct(
        public string $code,
        public string $label,
        public int|float|null $value,
        public string $unit = '',
        public string $goal = '',
        public string $note = '',
    ) {}

    public function display(): string
    {
        return match (true) {
            $this->value === null => 'Sin dato',
            $this->unit === 'Q' => Money::gtq((int) $this->value)->format(),
            $this->unit === '' => (string) $this->value,
            default => number_format((float) $this->value, 1).' '.$this->unit,
        };
    }
}
