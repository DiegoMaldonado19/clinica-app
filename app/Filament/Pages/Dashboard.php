<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Analytics\Application\ReportService;
use App\Analytics\Domain\Port\ReportExporter;
use App\Analytics\Domain\Report;
use App\Analytics\Domain\ReportPeriod;
use App\Models\Service;
use App\Shared\Domain\Clock\ClockInterface;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
        $custom = fn (Get $get): bool => $get('period') === 'custom';

        return $schema->components([
            Select::make('period')->label('Periodo')
                ->options(ReportPeriod::PRESETS + ['custom' => 'Rango personalizado'])
                ->default('month')
                ->selectablePlaceholder(false)
                // El rango personalizado arranca con las fechas del periodo que se estaba viendo.
                ->afterStateUpdated(function (?string $old, ?string $state, Set $set): void {
                    if ($state === 'custom') {
                        $period = self::period(['period' => $old]);
                        $set('from', $period->from->format('Y-m-d'));
                        $set('to', $period->to->modify('-1 day')->format('Y-m-d'));
                    }
                }),
            DatePicker::make('from')->label('Desde')->visible($custom)
                ->minDate(fn (Get $get): ?string => self::shift($get('to'), '-'.ReportPeriod::LONGEST_RANGE.' +1 day'))
                ->maxDate(fn (Get $get): ?string => $get('to')),
            DatePicker::make('to')->label('Hasta')->visible($custom)
                ->minDate(fn (Get $get): ?string => $get('from'))
                ->maxDate(fn (Get $get): ?string => self::shift($get('from'), '+'.ReportPeriod::LONGEST_RANGE.' -1 day')),
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
            self::period($filters ?? []),
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

    /**
     * El filtro llega del navegador, y la URL y la sesión lo guardan: un rango que no
     * sirve cae en «Este mes», como un periodo desconocido.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function period(array $filters): ReportPeriod
    {
        $timezone = new DateTimeZone(config('clinic.timezone'));
        $key = (string) ($filters['period'] ?? 'month');
        $range = $key === 'custom'
            ? ReportPeriod::tryDays((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''), $timezone)
            : null;

        return $range ?? ReportPeriod::preset($key, app(ClockInterface::class)->now(), $timezone);
    }

    /** El día a `$modifier` de otro: el calendario no deja elegir un rango mayor que el permitido. */
    private static function shift(mixed $day, string $modifier): ?string
    {
        return filled($day) ? CarbonImmutable::parse((string) $day)->modify($modifier)->toDateString() : null;
    }
}
