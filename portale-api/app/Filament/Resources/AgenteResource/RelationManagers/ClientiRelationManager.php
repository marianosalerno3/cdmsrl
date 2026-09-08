<?php

namespace App\Filament\Resources\AgenteResource\RelationManagers;

use App\Enums\RichiestaStato;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClientiRelationManager extends RelationManager
{
    protected static string $relationship = 'clienti';

    protected static ?string $title = 'Clienti in portafoglio';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ragione_sociale')
            ->columns([
                Tables\Columns\TextColumn::make('codice_cliente_erp')->label('Cod. ERP'),
                Tables\Columns\TextColumn::make('ragione_sociale')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('partita_iva')->label('P. IVA'),
                Tables\Columns\TextColumn::make('stato_richiesta')->badge(),
                Tables\Columns\IconColumn::make('attivo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stato_richiesta')->options(RichiestaStato::class),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('apri')
                    ->url(fn ($record) => \App\Filament\Resources\ClienteResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ]);
    }
}
