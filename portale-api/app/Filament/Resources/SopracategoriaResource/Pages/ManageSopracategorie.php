<?php

namespace App\Filament\Resources\SopracategoriaResource\Pages;

use App\Filament\Resources\SopracategoriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSopracategorie extends ManageRecords
{
    protected static string $resource = SopracategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
