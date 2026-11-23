<?php

namespace App\Filament\Resources\CollezioneResource\Pages;

use App\Filament\Resources\CollezioneResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCollezioni extends ManageRecords
{
    protected static string $resource = CollezioneResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
