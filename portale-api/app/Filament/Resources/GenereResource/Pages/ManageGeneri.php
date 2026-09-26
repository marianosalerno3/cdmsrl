<?php

namespace App\Filament\Resources\GenereResource\Pages;

use App\Filament\Resources\GenereResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGeneri extends ManageRecords
{
    protected static string $resource = GenereResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
