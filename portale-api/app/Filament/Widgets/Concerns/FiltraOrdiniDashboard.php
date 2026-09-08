<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\OrdineB2B;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applica i filtri della Dashboard (stagione, intervallo date) a una query ordini.
 * Da usare nei widget insieme a Filament\Widgets\Concerns\InteractsWithPageFilters,
 * che espone $this->filters.
 */
trait FiltraOrdiniDashboard
{
    protected function ordiniQuery(): Builder
    {
        $filters = $this->filters ?? [];
        $stagione = $filters['stagione'] ?? null;
        $da = $filters['data_inizio'] ?? null;
        $a = $filters['data_fine'] ?? null;

        return OrdineB2B::query()
            ->when($da, fn (Builder $q) => $q->whereDate('data_ordine', '>=', $da))
            ->when($a, fn (Builder $q) => $q->whereDate('data_ordine', '<=', $a))
            ->when($stagione, fn (Builder $q) => $q->whereHas(
                'righe.variante.prodotto.stagione',
                fn (Builder $q) => $q->where('codice', $stagione),
            ));
    }
}
