<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrdineB2BResource\Pages;
use App\Filament\Resources\OrdineB2BResource\RelationManagers\RigheRelationManager;
use App\Jobs\InviaOrdineErp;
use App\Mail\OrdineConfermaMail;
use App\Models\OrdineB2B;
use App\Services\OrderDocumentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class OrdineB2BResource extends Resource
{
    protected static ?string $model = OrdineB2B::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Ordini';

    protected static ?string $navigationLabel = 'Ordini B2B';

    protected static ?string $modelLabel = 'Ordine B2B';

    protected static ?string $pluralModelLabel = 'Ordini B2B';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // gli ordini nascono dalla SPA agenti
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informazioni Ordine')->columns(4)->schema([
                Forms\Components\TextInput::make('numero')->disabled(),
                Forms\Components\Select::make('stato')->options(OrderStatus::class)->required(),
                Forms\Components\DatePicker::make('data_ordine')->required()->displayFormat('d/m/Y'),
                Forms\Components\TextInput::make('totale')->numeric()->prefix('€')->disabled(),
            ]),

            Forms\Components\Section::make('Cliente')->columns(2)->schema([
                Forms\Components\Select::make('cliente_id')
                    ->relationship('cliente', 'ragione_sociale')->searchable()->preload()->disabled(),
                Forms\Components\TextInput::make('cliente_nome')->required(),
                Forms\Components\TextInput::make('cliente_email')->email(),
                Forms\Components\TextInput::make('cliente_telefono')->tel(),
                Forms\Components\Textarea::make('indirizzo_spedizione')->required()->rows(2)->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Dettagli')->columns(2)->schema([
                Forms\Components\Select::make('metodo_pagamento')->options(PaymentMethod::class),
                Forms\Components\TextInput::make('listino_applicato')->disabled(),
                Forms\Components\Textarea::make('note_agente')->rows(3)->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Dati WordPress / e-commerce')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('woocommerce_order_id')->numeric()->label('ID Ordine WordPress')->disabled(),
                    Forms\Components\TextInput::make('shopify_order_id')->label('ID Ordine Shopify')->disabled(),
                    Forms\Components\Textarea::make('wordpress_payload')
                        ->label('Payload JSON')
                        ->formatStateUsing(fn ($state) => filled($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                        ->disabled()->rows(6)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Numero Ordine')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('cliente_nome')->label('Cliente')->searchable()->wrap()->limit(35),
                Tables\Columns\TextColumn::make('agente.nome')->label('Agente')->toggleable()->sortable(),
                Tables\Columns\IconColumn::make('email_conferma_inviata_at')->label('Email Inviata')
                    ->boolean()->state(fn (OrdineB2B $r) => $r->email_conferma_inviata_at !== null),
                Tables\Columns\TextColumn::make('stato')->badge(),
                Tables\Columns\TextColumn::make('totale')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('metodo_pagamento')->label('Pagamento')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('pagato_at')->label('Pagato')->boolean()
                    ->state(fn (OrdineB2B $r) => $r->pagato_at !== null)->toggleable(),
                Tables\Columns\IconColumn::make('inviato_erp_at')->label('ERP')->boolean()
                    ->state(fn (OrdineB2B $r) => $r->inviato_erp_at !== null)->toggleable(),
                Tables\Columns\TextColumn::make('data_ordine')->label('Data Ordine')->date('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('data_ordine', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('stato')->options(OrderStatus::class),
                Tables\Filters\SelectFilter::make('metodo_pagamento')->options(PaymentMethod::class),
                Tables\Filters\SelectFilter::make('agente')->relationship('agente', 'nome')->searchable(),
                Tables\Filters\Filter::make('data_ordine')
                    ->form([
                        Forms\Components\DatePicker::make('da'),
                        Forms\Components\DatePicker::make('a'),
                    ])
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query, array $data) => $query
                        ->when($data['da'] ?? null, fn ($q, $d) => $q->whereDate('data_ordine', '>=', $d))
                        ->when($data['a'] ?? null, fn ($q, $d) => $q->whereDate('data_ordine', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Visualizza'),
                Tables\Actions\EditAction::make()->label('Modifica'),

                Tables\Actions\Action::make('cambia_stato')
                    ->label('Cambia Stato')->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('stato')->options(OrderStatus::class)->required(),
                    ])
                    ->fillForm(fn (OrdineB2B $r) => ['stato' => $r->stato?->value])
                    ->action(function (OrdineB2B $r, array $data) {
                        $r->update(['stato' => $data['stato']]);
                        Notification::make()->success()->title('Stato aggiornato')->send();
                    }),

                Tables\Actions\Action::make('pdf')
                    ->label('Scarica PDF')->icon('heroicon-o-document-arrow-down')
                    ->action(function (OrdineB2B $r) {
                        $doc = app(OrderDocumentService::class);

                        return response()->streamDownload(
                            fn () => print ($doc->pdf($r)->output()),
                            $doc->filename($r),
                        );
                    }),

                Tables\Actions\Action::make('invia_mail')
                    ->label('Invia Mail Conferma')->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->disabled(fn (OrdineB2B $r) => blank($r->cliente_email))
                    ->action(function (OrdineB2B $r) {
                        Mail::to($r->cliente_email)->queue(new OrdineConfermaMail($r));
                        $r->update(['email_conferma_inviata_at' => now()]);
                        Notification::make()->success()->title('Email di conferma in coda')->send();
                    }),

                Tables\Actions\Action::make('invia_erp')
                    ->label('Invia a WinMino')->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(function (OrdineB2B $r) {
                        InviaOrdineErp::dispatch($r->id);
                        Notification::make()->success()->title('Invio a WinMino in coda')->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('invia_erp')
                        ->label('Invia a WinMino')->icon('heroicon-o-paper-airplane')
                        ->action(fn ($records) => $records->each(fn ($r) => InviaOrdineErp::dispatch($r->id))),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RigheRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdiniB2B::route('/'),
            'view' => Pages\ViewOrdineB2B::route('/{record}'),
            'edit' => Pages\EditOrdineB2B::route('/{record}/edit'),
        ];
    }
}
