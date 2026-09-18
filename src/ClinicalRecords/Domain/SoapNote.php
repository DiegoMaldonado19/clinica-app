<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain;

final readonly class SoapNote
{
    public function __construct(
        public string $subjective = '',
        public string $objective = '',
        public string $assessment = '',
        public string $plan = '',
    ) {}

    /** @return list<string> */
    public function missingComponents(): array
    {
        $labels = ['subjective' => 'Subjetivo', 'objective' => 'Objetivo', 'assessment' => 'Análisis', 'plan' => 'Plan'];

        return array_values(array_filter($labels, fn (string $label, string $field): bool => trim($this->{$field}) === '', ARRAY_FILTER_USE_BOTH));
    }

    public function isComplete(): bool
    {
        return $this->missingComponents() === [];
    }
}
