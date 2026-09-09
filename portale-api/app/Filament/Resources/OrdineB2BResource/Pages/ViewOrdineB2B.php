<?php

namespace App\Filament\Resources\OrdineB2BResource\Pages;

use App\Filament\Resources\OrdineB2BResource;
use App\Models\OrdineB2B;
use App\Services\OrderDocumentService;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrdineB2B extends ViewRecord
{
    protected static string $resource = OrdineB2BResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('pdf')
                ->label('Scarica PDF')->icon('heroicon-o-document-arrow-down')
                ->action(function (OrdineB2B $record) {
                    $doc = app(OrderDocumentService::class);

                    return response()->streamDownload(
                        fn () => print ($doc->pdf($record)->output()),
                        $doc->filename($record),
                    );
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Ordine')->columns(4)->schema([
                TextEntry::make('numero')->weight('bold'),
                TextEntry::make('stato')->badge(),
                TextEntry::make('data_ordine')->date('d/m/Y H:i'),
                TextEntry::make('metodo_pagamento')->badge(),
                TextEntry::make('agente.nome')->label('Agente'),
                TextEntry::make('listino_applicato')->label('Listino'),
                TextEntry::make('email_conferma_inviata_at')->label('Email conferma')->dateTime('d/m/Y H:i')->placeholder('non inviata'),
            ]),
            Section::make('Cliente')->columns(2)->schema([
                TextEntry::make('cliente_nome'),
                TextEntry::make('cliente_email'),
                TextEntry::make('cliente_telefono'),
                TextEntry::make('indirizzo_spedizione')->columnSpanFull(),
            ]),
            Section::make('Totali')->columns(4)->schema([
                TextEntry::make('subtotale')->money('EUR'),
                TextEntry::make('spese_spedizione')->money('EUR'),
                TextEntry::make('iva_importo')->label('IVA')->money('EUR'),
                TextEntry::make('totale')->money('EUR')->weight('bold')->size('lg'),
                TextEntry::make('totale_pezzi')->label('Totale pezzi'),
            ]),
            Section::make('Note agente')->schema([
                TextEntry::make('note_agente')->hiddenLabel()->placeholder('—'),
            ]),
        ]);
    }
}
