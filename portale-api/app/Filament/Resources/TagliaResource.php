<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagliaResource\Pages;
use App\Models\Taglia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TagliaResource extends Resource
{
    protected static ?string $model = Taglia::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $navigationGroup = 'Attributi Prodotto';

    protected static ?string $modelLabel = 'Taglia';

    protected static ?string $pluralModelLabel = 'Taglie';

    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('codice')
                ->maxLength(6)->label('Codice WinMino')
                ->helperText('Codice taglia ERP — usato nelle varianti degli ordini inviati a WinMino.'),
            Forms\Components\TextInput::make('ordine')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('codice')->label('Cod. WinMino')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('ordine')->sortable(),
            ])
            ->defaultSort('ordine')
            ->reorderable('ordine')
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
        return ['index' => Pages\ManageTaglie::route('/')];
    }
}
