<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SopracategoriaResource\Pages;
use App\Models\Sopracategoria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SopracategoriaResource extends Resource
{
    protected static ?string $model = Sopracategoria::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Attributi Prodotto';

    protected static ?string $modelLabel = 'Sopracategoria';

    protected static ?string $pluralModelLabel = 'Sopracategorie';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('ordine')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('categorie_count')->counts('categorie')->label('Categorie'),
                Tables\Columns\TextColumn::make('ordine')->sortable()->toggleable(),
            ])
            ->defaultSort('ordine')
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
        return ['index' => Pages\ManageSopracategorie::route('/')];
    }
}
