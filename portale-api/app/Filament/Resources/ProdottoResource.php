<?php

namespace App\Filament\Resources;

use App\Enums\ProdottoTipo;
use App\Filament\Resources\ProdottoResource\Pages;
use App\Filament\Resources\ProdottoResource\RelationManagers\VariantiRelationManager;
use App\Jobs\SyncProdottoEcommerce;
use App\Models\Prodotto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Anagrafica prodotti. Fonte dati = WinMino (ERP): codice, nome, testi,
 * varianti, prezzi e giacenze arrivano dall'import e qui sono SOLA LETTURA.
 * Nel pannello si gestisce SOLO cio' che l'ERP non fornisce:
 *   - immagini prodotto e variante
 *   - flag "pubblica sul portale" (attivo)
 *   - push verso Shopify / WooCommerce
 */
class ProdottoResource extends Resource
{
    protected static ?string $model = Prodotto::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Catalogo';

    protected static ?string $modelLabel = 'Prodotto';

    protected static ?string $pluralModelLabel = 'Prodotti';

    protected static ?int $navigationSort = 2;

    /** Prodotti creati/modificati solo via import ERP: niente "New". */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dati da ERP (sola lettura)')
                ->description('Importati da WinMino. Per modificarli, aggiornare il gestionale e rilanciare la sincronizzazione.')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('codice')->disabled(),
                    Forms\Components\TextInput::make('nome')->disabled()->columnSpan(2),
                    Forms\Components\Textarea::make('descrizione')->disabled()->columnSpanFull()->rows(2),
                    Forms\Components\TextInput::make('composizione')->disabled(),
                    Forms\Components\TextInput::make('tessuto')->disabled(),
                    Forms\Components\TextInput::make('pacchetto')->disabled()->label('Pacchetto/Confezione'),
                    Forms\Components\TextInput::make('tipo')
                        ->disabled()->formatStateUsing(fn ($state) => $state instanceof ProdottoTipo ? $state->getLabel() : $state),
                    Forms\Components\TextInput::make('categoria.nome')->disabled()->label('Categoria'),
                    Forms\Components\TextInput::make('stagione.codice')->disabled()->label('Stagione'),
                ]),

            Forms\Components\Section::make('Gestione portale')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('attivo')
                        ->label('Pubblica sul portale agenti')
                        ->helperText('Se disattivo, il prodotto non compare nel catalogo della SPA.'),
                    Forms\Components\Select::make('genere_id')
                        ->relationship('genere', 'nome')->searchable()->preload()
                        ->label('Genere')
                        ->helperText('Non gestito da WinMino: assegnalo qui.'),
                    Forms\Components\Placeholder::make('mapping')
                        ->label('Mapping e-commerce')
                        ->content(fn (?Prodotto $record) => $record
                            ? 'Shopify: '.($record->shopify_product_id ?: '—').' · Woo: '.($record->woocommerce_product_id ?: '—')
                            : '—'),
                ]),

            Forms\Components\Section::make('Immagini prodotto')
                ->description('Unico dato non importato da WinMino.')
                ->schema([
                    Forms\Components\Repeater::make('immagini')
                        ->relationship()
                        ->orderColumn('ordine')
                        ->reorderable()
                        ->addActionLabel('Aggiungi immagine')
                        ->grid(3)
                        ->schema([
                            Forms\Components\FileUpload::make('percorso')
                                ->image()
                                ->directory('prodotti')
                                ->disk('public')
                                ->imageEditor()
                                ->required(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('immagini.percorso')
                    ->label('')->circular()->stacked()->limit(1)->disk('public'),
                Tables\Columns\TextColumn::make('codice')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('nome')->searchable()->wrap()->limit(45),
                Tables\Columns\TextColumn::make('categoria.nome')->label('Categoria')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('stagione.codice')->label('Stagione')->badge()->sortable(),
                Tables\Columns\TextColumn::make('tipo')->badge(),
                Tables\Columns\TextColumn::make('varianti_count')->counts('varianti')->label('Varianti'),
                Tables\Columns\TextColumn::make('giacenza_totale')->label('Giac. Tot.')
                    ->state(fn (Prodotto $r) => $r->varianti->sum('quantita')),
                Tables\Columns\TextColumn::make('immagini_count')->counts('immagini')->label('Img')
                    ->badge()->color(fn ($state) => $state > 0 ? 'success' : 'warning'),
                Tables\Columns\IconColumn::make('attivo')->boolean()->label('Pubbl.'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stagione')->relationship('stagione', 'codice'),
                Tables\Filters\SelectFilter::make('categoria')->relationship('categoria', 'nome'),
                Tables\Filters\TernaryFilter::make('attivo')->label('Pubblicato'),
                Tables\Filters\Filter::make('senza_immagini')
                    ->label('Senza immagini')
                    ->query(fn ($q) => $q->doesntHave('immagini')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Immagini / Portale'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('shopify')
                        ->label('Invia a Shopify')->icon('heroicon-o-shopping-bag')
                        ->action(function (Prodotto $r) {
                            SyncProdottoEcommerce::dispatch($r->id, 'shopify');
                            Notification::make()->success()->title('Invio a Shopify in coda')->send();
                        }),
                    Tables\Actions\Action::make('woocommerce')
                        ->label('Invia a WooCommerce')->icon('heroicon-o-globe-alt')
                        ->action(function (Prodotto $r) {
                            SyncProdottoEcommerce::dispatch($r->id, 'woocommerce');
                            Notification::make()->success()->title('Invio a WooCommerce in coda')->send();
                        }),
                ])->label('Sincronizza')->icon('heroicon-m-arrow-path')->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pubblica')
                        ->icon('heroicon-o-eye')
                        ->action(fn ($records) => $records->each->update(['attivo' => true])),
                    Tables\Actions\BulkAction::make('nascondi')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => $records->each->update(['attivo' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [VariantiRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdotti::route('/'),
            'edit' => Pages\EditProdotto::route('/{record}/edit'),
        ];
    }
}
