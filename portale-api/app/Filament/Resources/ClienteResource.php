<?php

namespace App\Filament\Resources;

use App\Enums\ClienteTipo;
use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Vendite';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clienti';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::where('stato_richiesta', RichiestaStato::InAttesa)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Anagrafica')->columns(2)->schema([
                Forms\Components\Select::make('agente_id')
                    ->relationship('agente', 'nome')->searchable()->preload()->label('Agente'),
                Forms\Components\Select::make('tipo')->options(ClienteTipo::class)->default('b2b')->required(),
                Forms\Components\TextInput::make('ragione_sociale')->maxLength(255)->columnSpanFull(),
                Forms\Components\TextInput::make('nome')->maxLength(255),
                Forms\Components\TextInput::make('cognome')->maxLength(255),
                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\TextInput::make('telefono')->tel(),
            ]),
            Forms\Components\Section::make('Fiscale')->columns(2)->schema([
                Forms\Components\TextInput::make('partita_iva')->maxLength(20),
                Forms\Components\TextInput::make('codice_fiscale')->maxLength(20),
                Forms\Components\TextInput::make('codice_sdi')->maxLength(12),
                Forms\Components\TextInput::make('pec')->email(),
                Forms\Components\TextInput::make('codice_cliente_erp')->label('Codice cliente ERP'),
            ]),
            Forms\Components\Section::make('Sede')->columns(3)->schema([
                Forms\Components\TextInput::make('indirizzo')->columnSpan(2),
                Forms\Components\TextInput::make('cap')->maxLength(16),
                Forms\Components\TextInput::make('citta'),
                Forms\Components\TextInput::make('provincia')->maxLength(4),
                Forms\Components\TextInput::make('nazione')->default('IT')->maxLength(2),
            ]),
            Forms\Components\Section::make('Commerciale')->columns(3)->schema([
                Forms\Components\Select::make('stato_richiesta')
                    ->options(RichiestaStato::class)->default('in_attesa')->required(),
                Forms\Components\Select::make('tipo_listino')
                    ->options(ListinoTipo::class)->label('Tipo listino')
                    ->helperText('Override del listino agente. Vuoto = usa quello dell\'agente.'),
                Forms\Components\Toggle::make('attivo')->default(true),
                Forms\Components\Toggle::make('contrassegno_abilitato')->label('Contrassegno abilitato'),
            ]),
            Forms\Components\Section::make('Consenso privacy')->schema([
                Forms\Components\Placeholder::make('consenso')->label('')->content(fn (?Cliente $record): string => $record?->privacy_accettata_at
                    ? 'Informativa privacy e condizioni d\'uso accettate il '.$record->privacy_accettata_at->format('d/m/Y H:i')
                        .' (versione '.($record->privacy_versione ?: '—').')'
                    : 'Nessun consenso registrato: cliente importato da ERP o inserito da un agente.'),
            ])->visibleOn('edit'),
            Forms\Components\Section::make('Comunicazioni commerciali')->schema([
                Forms\Components\Placeholder::make('comunicazioni')->label('')->content(fn (?Cliente $record): string => match (true) {
                    $record?->consenso_comunicazioni_revocato_at !== null => 'Consenso revocato il '.$record->consenso_comunicazioni_revocato_at->format('d/m/Y H:i').': non riceve email commerciali.',
                    $record?->consenso_comunicazioni_at !== null => 'Consenso dato il '.$record->consenso_comunicazioni_at->format('d/m/Y H:i').'.',
                    default => 'Nessun consenso: non riceve email commerciali (clienti importati da ERP o registrati prima del consenso).',
                }),
            ])->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('agente.nome')->label('Agente')->toggleable()->sortable(),
                Tables\Columns\TextColumn::make('codice_cliente_erp')->label('Cod. ERP')->searchable(),
                Tables\Columns\TextColumn::make('tipo')->badge(),
                Tables\Columns\TextColumn::make('ragione_sociale')->searchable()->wrap()->limit(40),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('stato_richiesta')->badge()->label('Stato Richiesta'),
                Tables\Columns\TextColumn::make('partita_iva')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('tipo_listino')->label('Listino')->badge()->toggleable(),
                Tables\Columns\IconColumn::make('attivo')->boolean(),
                Tables\Columns\TextColumn::make('privacy_accettata_at')->label('Consenso privacy')
                    ->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('comunicazioni')->label('Comunicazioni')->boolean()
                    ->state(fn (Cliente $r) => $r->haConsensoComunicazioni())->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('accesso')->label('Accesso portale')->boolean()
                    ->state(fn (Cliente $r) => $r->puoAccedere())->toggleable(),
                Tables\Columns\IconColumn::make('contrassegno_abilitato')->label('Contrassegno')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('sincronizzato_erp_at')->label('Sync ERP')->dateTime('d/m/Y H:i')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stato_richiesta')->options(RichiestaStato::class),
                Tables\Filters\SelectFilter::make('tipo')->options(ClienteTipo::class),
                Tables\Filters\SelectFilter::make('agente')->relationship('agente', 'nome')->searchable(),
                Tables\Filters\TernaryFilter::make('attivo'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('esporta_excel')
                    ->label('Esporta Excel (Tutti)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => static::exportCsv()),
            ])
            ->actions([
                Tables\Actions\Action::make('approva')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Cliente $r) => $r->stato_richiesta === RichiestaStato::InAttesa)
                    ->requiresConfirmation()
                    ->modalDescription('Il cliente potrà accedere al portale con l\'email e la password scelte in fase di registrazione.')
                    ->action(function (Cliente $r) {
                        // l'email di conferma parte da sola (vedi Cliente::booted) quando il cliente può accedere
                        $r->update(['stato_richiesta' => RichiestaStato::Approvato, 'attivo' => true]);

                        if (! filled($r->password)) {
                            // registrato dall'agente o importato da ERP: nessuna password scelta dal cliente
                            Notification::make()->warning()->title('Cliente approvato, ma senza accesso')
                                ->body('Non ha una password: usa "Imposta accesso" per abilitarlo al portale.')->send();

                            return;
                        }

                        Notification::make()->success()->title('Cliente approvato')
                            ->body(filled($r->email) ? 'Gli è stata inviata un\'email di conferma.' : null)->send();
                    }),
                Tables\Actions\Action::make('rifiuta')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Cliente $r) => $r->stato_richiesta === RichiestaStato::InAttesa)
                    ->requiresConfirmation()
                    ->action(function (Cliente $r) {
                        $r->update(['stato_richiesta' => RichiestaStato::Rifiutato, 'attivo' => false]);
                        $r->tokens()->delete();
                        Notification::make()->success()->title('Registrazione rifiutata')->send();
                    }),
                Tables\Actions\Action::make('imposta_accesso')
                    ->label('Imposta accesso')
                    ->icon('heroicon-o-key')->color('gray')
                    ->modalHeading('Imposta la password di accesso al portale')
                    ->modalDescription('Per clienti già in anagrafica (ERP) o registrati da un agente. Il cliente verrà approvato e attivato; comunicagli tu email e password.')
                    ->form([
                        Forms\Components\TextInput::make('password')->label('Nuova password')
                            ->password()->revealable()->required()->minLength(8),
                    ])
                    ->action(function (Cliente $r, array $data) {
                        if (! filled($r->email)) {
                            Notification::make()->danger()->title('Manca l\'email')
                                ->body('L\'email è il login del cliente: inseriscila nella scheda e riprova.')->send();

                            return;
                        }

                        $r->update([
                            'password' => $data['password'],
                            'stato_richiesta' => RichiestaStato::Approvato,
                            'attivo' => true,
                        ]);
                        $r->tokens()->delete(); // chiude le sessioni aperte con la vecchia password
                        Notification::make()->success()->title('Accesso impostato')
                            ->body("Login: {$r->email}")->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    protected static function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = ['Cod. ERP', 'Tipo', 'Ragione sociale', 'Email', 'P. IVA', 'Cod. fiscale', 'Stato', 'Listino', 'Agente', 'Attivo'];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ';');
            Cliente::with('agente')->chunk(500, function ($clienti) use ($out) {
                foreach ($clienti as $c) {
                    fputcsv($out, [
                        $c->codice_cliente_erp,
                        $c->tipo?->value,
                        $c->denominazione,
                        $c->email,
                        $c->partita_iva,
                        $c->codice_fiscale,
                        $c->stato_richiesta?->value,
                        $c->tipo_listino?->value,
                        $c->agente?->nome,
                        $c->attivo ? 'si' : 'no',
                    ], ';');
                }
            });
            fclose($out);
        }, 'clienti-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClienti::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
