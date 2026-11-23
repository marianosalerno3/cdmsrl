<?php

namespace App\Filament\Resources;

use App\Enums\Linea;
use App\Filament\Resources\CollezioneResource\Pages;
use App\Models\Collezione;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CollezioneResource extends Resource
{
    protected static ?string $model = Collezione::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Configurazione';

    protected static ?string $modelLabel = 'Collezione';

    protected static ?string $pluralModelLabel = 'Collezioni (stagione + linea)';

    protected static ?string $navigationLabel = 'Collezioni';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('stagione_id')->label('Stagione')
                ->relationship('stagione', 'codice')->required()->searchable()->preload(),
            Forms\Components\Select::make('linea')->label('Linea')->options(Linea::class)->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Forms\Get $get) => $rule->where('stagione_id', $get('stagione_id')))
                ->validationMessages(['unique' => 'Questa collezione esiste già.']),
            Forms\Components\Toggle::make('programmata')->label('Programmata')->live()
                ->helperText('Spenta = pronto consegna: spedizione calcolata in automatico e quantità entro la giacenza. Accesa = ordini programmati: spedizione concordata con il commerciale e quantità libere.'),
            Forms\Components\DatePicker::make('data_partenza')->label('Partenza spedizioni')
                ->visible(fn (Forms\Get $get) => (bool) $get('programmata'))
                ->helperText('Da inserire quando è nota: viene mostrata a clienti e agenti.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('linea')->label('Linea')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('stagione.codice')->label('Stagione')->sortable()->weight('bold'),
                Tables\Columns\ToggleColumn::make('programmata')->label('Programmata'),
                Tables\Columns\TextColumn::make('data_partenza')->label('Partenza spedizioni')->date('d/m/Y')->placeholder('—'),
            ])
            ->defaultSort('stagione.codice', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('programmata')->label('Programmata'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCollezioni::route('/')];
    }
}
