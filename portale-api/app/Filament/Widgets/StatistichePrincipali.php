<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltraOrdiniDashboard;
use App\Settings\IntegrationSettings;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatistichePrincipali extends BaseWidget
{
    use FiltraOrdiniDashboard;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $base = $this->ordiniQuery();

        $totaleOrdini = (clone $base)->count();
        $fatturato = (float) (clone $base)->sum('totale');

        $provvigioni = (float) (clone $base)
            ->join('agenti', 'agenti.id', '=', 'ordini_b2b.agente_id')
            ->sum(\Illuminate\Support\Facades\DB::raw('ordini_b2b.totale * agenti.commissione_perc / 100'));

        $erp = app(IntegrationSettings::class);
        $shopifyOk = filled($erp->shopify_shop_domain) && filled($erp->shopify_access_token);

        return [
            Stat::make('Totale Ordini', number_format($totaleOrdini, 0, ',', '.'))
                ->description('Numero ordini nel periodo')
                ->color('primary'),

            Stat::make('Fatturato Totale', '€ '.number_format($fatturato, 2, ',', '.'))
                ->description('Fatturato ordini nel periodo')
                ->color('success'),

            Stat::make('Totale Provvigioni', '€ '.number_format($provvigioni, 2, ',', '.'))
                ->description('Provvigioni agenti nel periodo')
                ->color('warning'),

            Stat::make('Stato Shopify', $shopifyOk ? 'Connesso' : 'Da configurare')
                ->description($shopifyOk ? 'Pronto all\'uso' : 'Configurazioni Sistema')
                ->color($shopifyOk ? 'success' : 'gray'),
        ];
    }
}
