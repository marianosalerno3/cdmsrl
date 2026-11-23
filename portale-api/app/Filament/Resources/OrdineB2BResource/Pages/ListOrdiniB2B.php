<?php

namespace App\Filament\Resources\OrdineB2BResource\Pages;

use App\Filament\Resources\OrdineB2BResource;
use Filament\Resources\Pages\ListRecords;

class ListOrdiniB2B extends ListRecords
{
    protected static string $resource = OrdineB2BResource::class;

    // il pannello mostra solo gli ordini B2B: gli ordini di Shopify non si importano più (vedi OrdineB2BResource::getEloquentQuery)
}
