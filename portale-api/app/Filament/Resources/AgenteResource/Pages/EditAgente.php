<?php

namespace App\Filament\Resources\AgenteResource\Pages;

use App\Filament\Resources\AgenteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgente extends EditRecord
{
    protected static string $resource = AgenteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
