<?php

namespace App\Filament\Resources\SostituzioneResource\Pages;

use App\Filament\Resources\SostituzioneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSostituzione extends EditRecord
{
    protected static string $resource = SostituzioneResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
