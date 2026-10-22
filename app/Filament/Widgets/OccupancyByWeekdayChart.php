<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Analytics\Domain\Report;
use App\Filament\Pages\Dashboard;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OccupancyByWeekdayChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Ocupación por día';

    protected ?string $description = 'Porcentaje de las horas publicadas, sin los bloqueos de agenda.';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $weekdays = Dashboard::report($this->pageFilters)->occupancyByWeekday;

        return [
            'datasets' => [[
                'label' => '%',
                'data' => array_column($weekdays, 'rate'),
                'backgroundColor' => AdminPanelProvider::COLORS['primary'][400],
            ]],
            'labels' => array_values(array_intersect_key(Report::WEEKDAYS, $weekdays)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['max' => 100]]];
    }
}
