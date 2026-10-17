<?php

namespace App\Filament\Resources\ProdottoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Varianti del prodotto. Dati da WinMino = sola lettura.
 * Modificabile solo il set di immagini della singola variante.
 */
class VariantiRelationManager extends RelationManager
{
    protected static string $relationship = 'varianti';

    protected static ?string $title = 'Varianti (da WinMino — sola lettura)';

    public function isReadOnly(): bool
    {
        return false; // gestiamo noi quali azioni esporre
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Repeater::make('immagini')
                ->relationship()
                ->orderColumn('ordine')
                ->reorderable()
                ->addActionLabel('Aggiungi immagine')
                ->grid(3)
                ->schema([
                    Forms\Components\FileUpload::make('percorso')
                        ->image()->directory('varianti')->disk('public')->imageEditor()->required(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('immagini.percorso')->label('')->disk('public')->limit(1),
                Tables\Columns\TextColumn::make('sku')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('taglia.nome')->label('Taglia'),
                Tables\Columns\TextColumn::make('colore.nome')->label('Colore'),
                Tables\Columns\TextColumn::make('prezzo')->money('EUR')->label('Prezzo base'),
                Tables\Columns\TextColumn::make('quantita')->label('Giacenza')
                    ->badge()->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('immagini_count')->counts('immagini')->label('Img'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Immagini')
                    ->icon('heroicon-o-photo')
                    ->modalHeading(fn ($record) => "Immagini variante {$record->sku}"),
            ])
            ->paginated([10, 25, 50]);
    }
}
