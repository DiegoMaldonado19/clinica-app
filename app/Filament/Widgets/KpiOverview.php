<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Analytics\Domain\Kpi;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Concerns\RefreshesFromDatabase;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters, RefreshesFromDatabase;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    // `getHeading()` de las estadisticas solo admite texto: el icono va en la seccion.
    public function getSectionContentComponent(): Component
    {
        /** @var Section $section */
        $section = parent::getSectionContentComponent();

        return $section->heading($this->headingWithRefresh('Indicadores'));
    }

    protected function getStats(): array
    {
        return array_map(
            fn (Kpi $kpi): Stat => Stat::make(ltrim("{$kpi->code} · {$kpi->label}", ' ·'), $kpi->display())
                ->description(implode(' · ', array_filter([$kpi->goal, $kpi->note])) ?: null),
            Dashboard::report($this->pageFilters)->indicators,
        );
    }
}
