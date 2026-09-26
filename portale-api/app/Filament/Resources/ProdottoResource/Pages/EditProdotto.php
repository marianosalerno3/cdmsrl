<?php

namespace App\Filament\Resources\ProdottoResource\Pages;

use App\Filament\Resources\ProdottoResource;
use Filament\Resources\Pages\EditRecord;

class EditProdotto extends EditRecord
{
    protected static string $resource = ProdottoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
