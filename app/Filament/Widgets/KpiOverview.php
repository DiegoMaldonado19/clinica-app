<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Analytics\Domain\Kpi;
use App\Filament\Pages\Dashboard;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return array_map(
            fn (Kpi $kpi): Stat => Stat::make(ltrim("{$kpi->code} · {$kpi->label}", ' ·'), $kpi->display())
                ->description(implode(' · ', array_filter([$kpi->goal, $kpi->note])) ?: null),
            Dashboard::report($this->pageFilters)->indicators,
        );
    }
}
