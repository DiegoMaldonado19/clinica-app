<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueByServiceChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Ingreso por servicio';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAbility('report.view.financial');
    }

    protected function getData(): array
    {
        $revenue = Dashboard::report($this->pageFilters)->revenueByService;

        return [
            'datasets' => [[
                'label' => 'Quetzales',
                'data' => array_map(fn (int $cents): float => $cents / 100, array_values($revenue)),
                'backgroundColor' => AdminPanelProvider::COLORS['primary'][600],
            ]],
            'labels' => array_keys($revenue),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }
}
