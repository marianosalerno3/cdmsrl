<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PacchettoResource\Pages;
use App\Models\Pacchetto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Pacchetti del catalogo, creati da CDM. Si assegnano ai prodotti (uno per prodotto) da Catalogo → Prodotti
 * e servono a filtrare e a ordinare il catalogo degli agenti. L'ordine tra pacchetti si cambia trascinando le righe.
 */
class PacchettoResource extends Resource
{
    protected static ?string $model = Pacchetto::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Attributi Prodotto';

    protected static ?string $modelLabel = 'Pacchetto';

    protected static ?string $pluralModelLabel = 'Pacchetti';

    protected static ?int $navigationSort = 14;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('ordine')->numeric()->default(0)->minValue(0)
                ->helperText('Più è basso, prima compare nel catalogo ordinato per pacchetto. Puoi anche trascinare le righe nell\'elenco.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('prodotti_count')->counts('prodotti')->label('Prodotti'),
                Tables\Columns\TextColumn::make('ordine')->sortable()->toggleable(),
            ])
            ->defaultSort('ordine')
            ->reorderable('ordine')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('I prodotti di questo pacchetto restano nel catalogo, ma senza pacchetto.'),
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
            'index' => Pages\ManagePacchetti::route('/'),
        ];
    }
}
