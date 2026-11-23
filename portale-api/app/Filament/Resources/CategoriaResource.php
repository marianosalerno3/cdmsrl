<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaResource\Pages;
use App\Models\Categoria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Attributi Prodotto';

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorie';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\Select::make('sopracategoria_id')
                ->relationship('sopracategoria', 'nome')
                ->searchable()->preload()->label('Sopracategoria'),
            Forms\Components\TextInput::make('ordine')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sopracategoria.nome')->label('Sopracategoria')->sortable(),
                Tables\Columns\TextColumn::make('prodotti_count')->counts('prodotti')->label('Prodotti'),
                Tables\Columns\TextColumn::make('ordine')->sortable()->toggleable(),
            ])
            ->defaultSort('nome')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategorie::route('/'),
        ];
    }
}
