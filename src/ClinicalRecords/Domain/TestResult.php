<?php

declare(strict_types=1);

namespace App\ClinicalRecords\Domain;

final readonly class TestResult
{
    public function __construct(
        public int $score,
        public string $severity,
        /** El PHQ-9 con el item 9 mayor que 0 pide revision urgente de la psicologa. */
        public bool $urgent,
    ) {}
}
