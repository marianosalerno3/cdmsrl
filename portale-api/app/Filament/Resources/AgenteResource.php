<?php

namespace App\Filament\Resources;

use App\Enums\ListinoTipo;
use App\Filament\Resources\AgenteResource\Pages;
use App\Models\Agente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class AgenteResource extends Resource
{
    protected static ?string $model = Agente::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Vendite';

    protected static ?string $modelLabel = 'Agente';

    protected static ?string $pluralModelLabel = 'Agenti';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Anagrafica')->columns(2)->schema([
                Forms\Components\TextInput::make('codice_agente')
                    ->required()->maxLength(50)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('nome')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('telefono')->tel()->maxLength(40),
            ]),
            Forms\Components\Section::make('Commerciale')->columns(3)->schema([
                Forms\Components\Select::make('listino_default')
                    ->options(ListinoTipo::class)->default('standard')->required()
                    ->label('Listino default'),
                Forms\Components\TextInput::make('commissione_perc')
                    ->numeric()->suffix('%')->default(0)->label('Commissione %'),
                Forms\Components\Toggle::make('attivo')->default(true),
            ]),
            Forms\Components\Section::make('Accesso portale')->columns(2)->schema([
                Forms\Components\TextInput::make('password')
                    ->password()->revealable()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText('Lascia vuoto in modifica per non cambiare la password.'),
                Forms\Components\TextInput::make('codice_erp')->maxLength(50)->label('Codice ERP'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codice_agente')->label('Codice')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('listino_default')->label('Listino')->badge(),
                Tables\Columns\TextColumn::make('commissione_perc')->label('Comm. %')->suffix('%')->sortable(),
                Tables\Columns\IconColumn::make('attivo')->boolean(),
                Tables\Columns\TextColumn::make('vendite_totali')
                    ->label('Vendite Totali')
                    ->money('EUR')
                    ->state(fn (Agente $r) => $r->vendite_totali),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('attivo'),
                Tables\Filters\SelectFilter::make('listino_default')->options(ListinoTipo::class)->label('Listino'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AgenteResource\RelationManagers\ClientiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgenti::route('/'),
            'create' => Pages\CreateAgente::route('/create'),
            'edit' => Pages\EditAgente::route('/{record}/edit'),
        ];
    }
}
