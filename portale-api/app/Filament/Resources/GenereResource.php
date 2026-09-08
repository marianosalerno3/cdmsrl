<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GenereResource\Pages;
use App\Models\Genere;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GenereResource extends Resource
{
    protected static ?string $model = Genere::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Attributi Prodotto';

    protected static ?string $modelLabel = 'Genere';

    protected static ?string $pluralModelLabel = 'Generi';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
            ])
            ->defaultSort('nome')
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
        return ['index' => Pages\ManageGeneri::route('/')];
    }
}
