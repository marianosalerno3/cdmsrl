<?php

namespace App\Filament\Resources\ColoreResource\Pages;

use App\Filament\Resources\ColoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageColori extends ManageRecords
{
    protected static string $resource = ColoreResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
