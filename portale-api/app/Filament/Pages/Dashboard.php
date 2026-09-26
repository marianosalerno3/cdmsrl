<?php

namespace App\Filament\Pages;

use App\Models\Stagione;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            \Filament\Forms\Components\Section::make()
                ->columns(3)
                ->schema([
                    Select::make('stagione')
                        ->label('Stagione')
                        ->options(fn () => Stagione::orderByDesc('ordine')->pluck('codice', 'codice'))
                        ->placeholder('Tutte'),
                    DatePicker::make('data_inizio')->label('Data Inizio')->displayFormat('d/m/Y'),
                    DatePicker::make('data_fine')->label('Data Fine')->displayFormat('d/m/Y'),
                ]),
        ]);
    }
}
