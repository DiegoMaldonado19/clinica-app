<?php

declare(strict_types=1);

use App\Analytics\Domain\Kpi;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\OccupancyByWeekdayChart;
use App\Filament\Widgets\RevenueByServiceChart;
use App\Models\User;
use App\Scheduling\Application\AppointmentTransitions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->role('admin')->create();
    $this->secretary = User::factory()->role('secretary')->create();
    $this->travelTo(gtAt('2026-09-15 10:00'));

    $transitions = app(AppointmentTransitions::class);
    $attended = requestAppointment('2026-09-18 15:00');
    $missed = requestAppointment('2026-09-18 16:00', 'otra@correo.test');

    foreach ([$attended, $missed] as $id) {
        $transitions->approve($id, $this->secretary->id);
        payAndBook($id, $this->secretary->id);
    }

    $this->travelTo(gtAt('2026-09-18 15:05'));
    $transitions->checkIn($attended, $this->secretary->id);
    $this->travelTo(gtAt('2026-09-18 16:30'));
    $transitions->markNoShow($missed, $this->secretary->id);
});

/** @return array<string, list<list<mixed>>> hoja => filas */
function xlsxSheets(string $base64): array
{
    $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, base64_decode($base64));
    $reader = new Reader;
    $reader->open($path);
    $sheets = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $sheets[$sheet->getName()][] = $row->toArray();
        }
    }

    $reader->close();
    unlink($path);

    return $sheets;
}

it('exporta a XLSX los mismos totales que muestra el tablero (CA-09)', function () {
    $this->actingAs($this->admin);
    $report = Dashboard::report(['period' => 'month']);
    $download = Livewire::test(Dashboard::class)->callAction('exportXlsx')->effects['download'];

    $sheets = xlsxSheets($download['content']);
    $exported = array_column(array_slice($sheets['Indicadores'], 2), 2, 1);
    $widget = Livewire::test(KpiOverview::class, ['pageFilters' => ['period' => 'month']]);

    foreach ($report->indicators as $kpi) {
        $widget->assertSee($kpi->display());
        expect($exported[$kpi->label])->toEqual(match (true) {
            $kpi->value === null => 'Sin dato',
            $kpi->unit === 'Q' => $kpi->value / 100,
            default => $kpi->value,
        });
    }

    $indicators = collect($report->indicators)->keyBy(fn (Kpi $kpi): string => $kpi->code ?: $kpi->label);

    expect($download['name'])->toBe('reporte-2026-09-01.xlsx')
        ->and($indicators['KPI-01']->value)->toBe(50.0)
        ->and($indicators['KPI-07']->value)->toBe(60000)
        ->and(end($sheets['Ingreso por servicio']))->toEqual(['Total', 600]);
});

it('exporta el PDF del reporte como application/pdf', function () {
    $download = Livewire::actingAs($this->admin)->test(Dashboard::class)
        ->callAction('exportPdf')
        ->assertFileDownloaded('reporte-2026-09-01.pdf', contentType: 'application/pdf')
        ->effects['download'];

    expect(base64_decode($download['content']))->toStartWith('%PDF-');
});

// F3-10: sin cache ni sondeo cada 5 s; cada reporte vuelve a consultar la base con su icono.
it('trae en cada reporte del tablero su icono de recarga y no se refresca solo', function (string $widget) {
    Livewire::actingAs($this->admin)->test($widget, ['pageFilters' => ['period' => 'month']])
        ->assertSeeHtml('wire:click="$refresh"')
        ->assertDontSeeHtml('wire:poll');
})->with([KpiOverview::class, RevenueByServiceChart::class, OccupancyByWeekdayChart::class]);

it('muestra a recepcion los indicadores operativos sin ninguna cifra de ingresos (HU-16)', function () {
    $this->actingAs($this->secretary)->get(Dashboard::getUrl())->assertSuccessful();

    Livewire::actingAs($this->secretary)->test(KpiOverview::class, ['pageFilters' => ['period' => 'month']])
        ->assertSee('Tasa de inasistencia')
        ->assertDontSee('Ingreso');

    $sheets = xlsxSheets(Livewire::test(Dashboard::class)->callAction('exportXlsx')->effects['download']['content']);

    expect(RevenueByServiceChart::canView())->toBeFalse()
        ->and($sheets)->not->toHaveKey('Ingreso por servicio')
        ->and(array_column($sheets['Indicadores'], 0))->not->toContain('KPI-07');
});
