<?php

namespace App\Filament\Resources\OrdineB2BResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RigheRelationManager extends RelationManager
{
    protected static string $relationship = 'righe';

    protected static ?string $title = 'Righe ordine';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prodotto_codice')->label('Codice'),
                Tables\Columns\TextColumn::make('prodotto_nome')->label('Prodotto')->wrap()->limit(40),
                Tables\Columns\TextColumn::make('taglia'),
                Tables\Columns\TextColumn::make('colore'),
                Tables\Columns\TextColumn::make('quantita')->label('Q.tà')->alignEnd(),
                Tables\Columns\TextColumn::make('prezzo_unitario')->money('EUR')->alignEnd(),
                Tables\Columns\TextColumn::make('totale_riga')->money('EUR')->alignEnd()->weight('bold'),
            ])
            ->paginated(false);
    }
}
