<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\Kpi;
use App\Analytics\Domain\Port\ReportExporter;
use App\Analytics\Domain\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** XLSX con openspout, que ya trae Filament, y PDF con dompdf. Montos en quetzales, no en centavos. */
final class ReportFileExporter implements ReportExporter
{
    public function toXlsx(Report $report): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'reporte');
        $bold = (new Style)->setFontBold();
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Indicadores');
        $writer->addRow(Row::fromValues([self::title($report)], $bold));
        $writer->addRow(Row::fromValues(['Código', 'Indicador', 'Valor', 'Unidad', 'Meta', 'Nota'], $bold));

        foreach ($report->indicators as $kpi) {
            $writer->addRow(Row::fromValues([$kpi->code, $kpi->label, self::cell($kpi), $kpi->unit, $kpi->goal, $kpi->note]));
        }

        if ($report->revenueByService !== []) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Ingreso por servicio');
            $writer->addRow(Row::fromValues(['Servicio', 'Ingreso cobrado (Q)'], $bold));

            foreach ($report->revenueByService as $service => $cents) {
                $writer->addRow(Row::fromValues([$service, $cents / 100]));
            }

            $writer->addRow(Row::fromValues(['Total', array_sum($report->revenueByService) / 100], $bold));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Ocupación por día');
        $writer->addRow(Row::fromValues(['Día', 'Horas publicadas', 'Horas ocupadas', 'Ocupación (%)'], $bold));

        foreach ($report->occupancyByWeekday as $weekday => $day) {
            $writer->addRow(Row::fromValues([Report::WEEKDAYS[$weekday], $day['published'], $day['occupied'], $day['rate']]));
        }

        $writer->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }

    public function toPdf(Report $report): string
    {
        // Sin subconjunto, dompdf incrusta DejaVu completa: 1.4 MB por una página.
        return Pdf::loadView('reports.kpi', ['report' => $report, 'title' => self::title($report)])
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public static function title(Report $report): string
    {
        $day = fn (DateTimeImmutable $date): string => Carbon::instance($date)->locale('es')->translatedFormat('j M Y');

        return 'Indicadores del '.$day($report->period->from).' al '.$day($report->period->to->modify('-1 day'))
            .' · '.($report->serviceName ?? 'Todos los servicios');
    }

    private static function cell(Kpi $kpi): int|float|string
    {
        return match (true) {
            $kpi->value === null => 'Sin dato',
            $kpi->unit === 'Q' => $kpi->value / 100,
            default => $kpi->value,
        };
    }
}
