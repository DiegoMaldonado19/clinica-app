<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Analytics\Application\ReportService;
use App\Analytics\Domain\Port\ReportExporter;
use App\Analytics\Domain\Report;
use App\Analytics\Domain\ReportPeriod;
use App\Models\Service;
use App\Shared\Domain\Clock\ClockInterface;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * D-05 · HU-14, HU-15 y HU-16. Los widgets y las dos exportaciones salen del
 * mismo `report()`: los totales no pueden diferir (CA-09).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Tablero';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAbility('report.view.operational');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')->label('Periodo')
                ->options(ReportPeriod::PRESETS)
                ->default('month')
                ->selectablePlaceholder(false),
            Select::make('service')->label('Servicio')
                ->options(fn (): array => Service::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('Todos los servicios'),
        ]);
    }

    /**
     * Recepción recibe el mismo reporte sin ninguna cifra de ingresos (HU-16).
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function report(?array $filters): Report
    {
        return app(ReportService::class)->build(
            ReportPeriod::preset((string) ($filters['period'] ?? 'month'), app(ClockInterface::class)->now(), new DateTimeZone(config('clinic.timezone'))),
            filled($filters['service'] ?? null) ? (string) $filters['service'] : null,
            (bool) auth()->user()?->hasAbility('report.view.financial'),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportXlsx')->label('Exportar Excel')->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->action(fn (): StreamedResponse => $this->download('xlsx')),
            Action::make('exportPdf')->label('Exportar PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->action(fn (): StreamedResponse => $this->download('pdf')),
        ];
    }

    private function download(string $format): StreamedResponse
    {
        $report = self::report($this->filters);
        $exporter = app(ReportExporter::class);
        $content = $format === 'pdf' ? $exporter->toPdf($report) : $exporter->toXlsx($report);

        return response()->streamDownload(fn () => print ($content), "reporte-{$report->period->from->format('Y-m-d')}.{$format}", [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
