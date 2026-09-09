<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StagioneResource\Pages;
use App\Models\Stagione;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StagioneResource extends Resource
{
    protected static ?string $model = Stagione::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Configurazione';

    protected static ?string $modelLabel = 'Stagione';

    protected static ?string $pluralModelLabel = 'Stagioni';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codice')
                ->required()->maxLength(50)->unique(ignoreRecord: true)
                ->helperText('Es. PE25, AI25, PE26, Programmato'),
            Forms\Components\TextInput::make('nome')->maxLength(255),
            Forms\Components\DatePicker::make('data_inizio'),
            Forms\Components\DatePicker::make('data_fine'),
            Forms\Components\Toggle::make('attiva')->default(true),
            Forms\Components\Toggle::make('programmata')
                ->helperText('Stagione di ordini programmati (evasione differita)'),
            Forms\Components\TextInput::make('ordine')->numeric()->default(0)
                ->helperText('Ordine di visualizzazione: valori alti in cima'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codice')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('nome')->toggleable(),
                Tables\Columns\IconColumn::make('attiva')->boolean(),
                Tables\Columns\IconColumn::make('programmata')->boolean(),
                Tables\Columns\TextColumn::make('prodotti_count')->counts('prodotti')->label('Prodotti'),
                Tables\Columns\TextColumn::make('data_inizio')->date('d/m/Y')->toggleable(),
                Tables\Columns\TextColumn::make('data_fine')->date('d/m/Y')->toggleable(),
            ])
            ->defaultSort('ordine', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStagioni::route('/')];
    }
}
