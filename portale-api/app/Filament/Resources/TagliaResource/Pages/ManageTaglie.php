<?php

namespace App\Filament\Resources\TagliaResource\Pages;

use App\Filament\Resources\TagliaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTaglie extends ManageRecords
{
    protected static string $resource = TagliaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
