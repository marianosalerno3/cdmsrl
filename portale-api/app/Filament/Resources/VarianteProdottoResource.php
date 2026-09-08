<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VarianteProdottoResource\Pages;
use App\Models\VarianteProdotto;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * "Dizionario Prodotti" — vista piatta di tutte le varianti (ricerca per SKU,
 * verifica giacenze/prezzi). Sola lettura: la fonte e' WinMino.
 */
class VarianteProdottoResource extends Resource
{
    protected static ?string $model = VarianteProdotto::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Catalogo';

    protected static ?string $navigationLabel = 'Dizionario Prodotti';

    protected static ?string $modelLabel = 'Variante';

    protected static ?string $pluralModelLabel = 'Varianti';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->searchable()->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('prodotto.codice')->label('Prodotto')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('prodotto.nome')->label('Descrizione')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('taglia.nome')->label('Taglia'),
                Tables\Columns\TextColumn::make('colore.nome')->label('Colore')->searchable(),
                Tables\Columns\TextColumn::make('prezzo')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('quantita')->label('Giacenza')->sortable()
                    ->badge()->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('barcode')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prodotto')
                    ->relationship('prodotto', 'codice')->searchable(),
                Tables\Filters\Filter::make('esaurite')
                    ->label('Solo esaurite')
                    ->query(fn ($q) => $q->where('quantita', '<=', 0)),
            ])
            ->defaultSort('sku')
            ->actions([
                Tables\Actions\Action::make('vai_al_prodotto')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (VarianteProdotto $r) => ProdottoResource::getUrl('edit', ['record' => $r->prodotto_id])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVarianti::route('/')];
    }
}
