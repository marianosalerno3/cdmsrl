<?php

namespace App\Filament\Resources\PacchettoResource\Pages;

use App\Filament\Resources\PacchettoResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePacchetti extends ManageRecords
{
    protected static string $resource = PacchettoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
