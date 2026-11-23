<?php

namespace App\Filament\Resources\StagioneResource\Pages;

use App\Filament\Resources\StagioneResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStagioni extends ManageRecords
{
    protected static string $resource = StagioneResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
