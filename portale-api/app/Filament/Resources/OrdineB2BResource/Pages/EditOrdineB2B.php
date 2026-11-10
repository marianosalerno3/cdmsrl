<?php

namespace App\Filament\Resources\OrdineB2BResource\Pages;

use App\Filament\Resources\OrdineB2BResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrdineB2B extends EditRecord
{
    protected static string $resource = OrdineB2BResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
