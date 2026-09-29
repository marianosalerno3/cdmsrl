<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraOrdiniDashboard;
use App\Models\OrdineRiga;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class OrdiniPerStagione extends ChartWidget
{
    use FiltraOrdiniDashboard;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Ordini per Stagione';

    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $ordineIds = $this->ordiniQuery()->pluck('id');

        $rows = OrdineRiga::query()
            ->whereIn('ordine_righe.ordine_b2b_id', $ordineIds)
            ->join('variante_prodotti', 'variante_prodotti.id', '=', 'ordine_righe.variante_prodotto_id')
            ->join('prodotti', 'prodotti.id', '=', 'variante_prodotti.prodotto_id')
            ->leftJoin('stagioni', 'stagioni.id', '=', 'prodotti.stagione_id')
            ->groupBy('stagioni.codice')
            ->select('stagioni.codice', DB::raw('COUNT(DISTINCT ordine_righe.ordine_b2b_id) as n'))
            ->orderBy('stagioni.codice')
            ->pluck('n', 'codice');

        return [
            'datasets' => [[
                'label' => 'Numero ordini',
                'data' => array_values($rows->toArray()),
                'backgroundColor' => '#22c55e',
            ]],
            'labels' => array_keys($rows->toArray()),
        ];
    }
}
