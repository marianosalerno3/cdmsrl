<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColoreResource\Pages;
use App\Models\Colore;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ColoreResource extends Resource
{
    protected static ?string $model = Colore::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Attributi Prodotto';

    protected static ?string $modelLabel = 'Colore';

    protected static ?string $pluralModelLabel = 'Colori';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\ColorPicker::make('hex')->label('Anteprima (hex)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('hex')->label(''),
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
            ])
            ->defaultSort('nome')
            ->searchable()
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
        return ['index' => Pages\ManageColori::route('/')];
    }
}
