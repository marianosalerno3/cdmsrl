<?php

namespace App\Filament\Resources\OrdineB2BResource\Pages;

use App\Filament\Resources\OrdineB2BResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrdiniB2B extends ListRecords
{
    protected static string $resource = OrdineB2BResource::class;

    public function getTabs(): array
    {
        return [
            'tutti' => Tab::make('Tutti gli Ordini'),
            'b2b' => Tab::make('Ordini B2B')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('shopify_order_id')),
            'shopify' => Tab::make('Ordini Shopify')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('shopify_order_id')),
        ];
    }
}
