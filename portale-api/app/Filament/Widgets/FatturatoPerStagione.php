<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraOrdiniDashboard;
use App\Models\OrdineRiga;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class FatturatoPerStagione extends ChartWidget
{
    use FiltraOrdiniDashboard;
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Fatturato Netto per Stagione';

    protected static ?int $sort = 3;

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
            ->select('stagioni.codice', DB::raw('SUM(ordine_righe.totale_riga) as fatturato'))
            ->orderBy('stagioni.codice')
            ->pluck('fatturato', 'codice');

        return [
            'datasets' => [[
                'label' => 'Fatturato netto €',
                'data' => array_map(fn ($v) => round((float) $v, 2), array_values($rows->toArray())),
                'backgroundColor' => '#6366f1',
            ]],
            'labels' => array_keys($rows->toArray()),
        ];
    }
}
