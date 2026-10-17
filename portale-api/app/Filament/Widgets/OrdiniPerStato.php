<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\Concerns\FiltraOrdiniDashboard;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OrdiniPerStato extends ChartWidget
{
    use FiltraOrdiniDashboard;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Ordini per Stato';

    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = $this->ordiniQuery()
            ->selectRaw('stato, count(*) as n')
            ->groupBy('stato')
            ->pluck('n', 'stato');

        $labels = [];
        $values = [];
        foreach ($rows as $stato => $n) {
            $labels[] = OrderStatus::tryFrom($stato)?->getLabel() ?? $stato;
            $values[] = $n;
        }

        return [
            'datasets' => [[
                'label' => 'Ordini',
                'data' => $values,
                'backgroundColor' => ['#38bdf8', '#6366f1', '#f59e0b', '#a855f7', '#22c55e', '#ef4444'],
            ]],
            'labels' => $labels,
        ];
    }
}
