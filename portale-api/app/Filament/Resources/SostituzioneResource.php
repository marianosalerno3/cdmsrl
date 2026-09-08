<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SostituzioneResource\Pages;
use App\Models\Sostituzione;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SostituzioneResource extends Resource
{
    protected static ?string $model = Sostituzione::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Vendite';

    protected static ?string $modelLabel = 'Sostituzione';

    protected static ?string $pluralModelLabel = 'Sostituzioni';

    protected static ?int $navigationSort = 3;

    protected const STATI = [
        'richiesta' => 'Richiesta',
        'approvata' => 'Approvata',
        'rifiutata' => 'Rifiutata',
        'evasa' => 'Evasa',
    ];

    public static function getNavigationBadge(): ?string
    {
        $n = static::getModel()::where('stato', 'richiesta')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema([
                Forms\Components\TextInput::make('numero')->disabled(),
                Forms\Components\Select::make('ordine_originale_id')
                    ->relationship('ordineOriginale', 'numero')->searchable()->required()->label('Ordine originale'),
                Forms\Components\Select::make('stato')->options(self::STATI)->default('richiesta')->required(),
                Forms\Components\Select::make('agente_id')->relationship('agente', 'nome')->searchable()->label('Agente'),
                Forms\Components\Select::make('cliente_id')->relationship('cliente', 'ragione_sociale')->searchable()->label('Cliente'),
            ]),
            Forms\Components\Textarea::make('motivazione')->rows(2)->columnSpanFull(),
            Forms\Components\Textarea::make('note')->rows(2)->columnSpanFull(),

            Forms\Components\Repeater::make('righe')
                ->relationship()
                ->label('Articoli')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('variante_resa_id')
                        ->relationship('varianteResa', 'sku')->searchable()->required()->label('Reso (SKU)'),
                    Forms\Components\Select::make('variante_richiesta_id')
                        ->relationship('varianteRichiesta', 'sku')->searchable()->label('In cambio (SKU)'),
                    Forms\Components\TextInput::make('quantita')->numeric()->minValue(1)->default(1)->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('ordineOriginale.numero')->label('Ordine originale')->searchable(),
                Tables\Columns\TextColumn::make('cliente.ragione_sociale')->label('Cliente')->limit(30)->toggleable(),
                Tables\Columns\TextColumn::make('agente.nome')->label('Agente')->toggleable(),
                Tables\Columns\TextColumn::make('stato')->badge()->formatStateUsing(fn ($s) => self::STATI[$s] ?? $s)
                    ->color(fn ($state) => match ($state) {
                        'richiesta' => 'warning',
                        'approvata' => 'info',
                        'evasa' => 'success',
                        'rifiutata' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('righe_count')->counts('righe')->label('Articoli'),
                Tables\Columns\TextColumn::make('created_at')->date('d/m/Y')->label('Richiesta il')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('stato')->options(self::STATI),
            ])
            ->actions([
                Tables\Actions\Action::make('avanza')
                    ->label('Cambia stato')->icon('heroicon-o-forward')
                    ->form([Forms\Components\Select::make('stato')->options(self::STATI)->required()])
                    ->fillForm(fn (Sostituzione $r) => ['stato' => $r->stato])
                    ->action(function (Sostituzione $r, array $data) {
                        $r->update(['stato' => $data['stato']]);
                        Notification::make()->success()->title('Stato aggiornato')->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSostituzioni::route('/'),
            'edit' => Pages\EditSostituzione::route('/{record}/edit'),
        ];
    }
}
