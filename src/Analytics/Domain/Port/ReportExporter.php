<?php

declare(strict_types=1);

namespace App\Analytics\Domain\Port;

use App\Analytics\Domain\Report;

/** Doc 08 de la Fase 2: el contenido binario del archivo exportado. */
interface ReportExporter
{
    public function toXlsx(Report $report): string;

    public function toPdf(Report $report): string;
}
