<?php

namespace App\Services\Erp\WinMino\Payload;

use App\Models\OrdineB2B;

/**
 * JSON per POST TDSOrdiniCLienti/AddOrdineCliente.
 *
 * Struttura: testata + RIGHE[] (una per ARTICOLO) + per riga VARIANTI[] (una per colore/taglia).
 * Obbligatori: CLIENTE, RIGHE[]; per riga ARTICOLO, UNITA, QUANTITA, PREZZO;
 * per variante COLORE, TAGLIA, QUANTITA.
 * Prezzo a livello ARTICOLO (confermato: nessun prezzo per variante).
 * Date d-m-Y, decimali col punto.
 */
class OrdinePayload
{
    public static function build(OrdineB2B $ordine): array
    {
        $ordine->loadMissing([
            'agente', 'cliente',
            'righe.variante.colore', 'righe.variante.taglia',
            'righe.variante.prodotto.stagione',
        ]);

        $listinoMap = config('winmino.listino_map', []);
        $pagamentoMap = config('winmino.pagamento_map', []);
        $def = config('winmino.defaults', []);

        // raggruppa le righe per codice articolo
        $gruppi = $ordine->righe->groupBy('prodotto_codice');

        $righe = [];
        $i = 0;
        foreach ($gruppi as $codArticolo => $items) {
            $i++;
            $prezzo = (float) $items->first()->prezzo_unitario;
            $unita = $items->first()->variante?->prodotto?->unita ?: ($def['unita'] ?? 'NR');

            $varianti = $items->map(fn ($r) => array_filter([
                'COLORE' => $r->variante?->colore?->codice ?: $r->colore,
                'TAGLIA' => $r->variante?->taglia?->codice ?: $r->taglia,
                'QUANTITA' => self::num($r->quantita, 0),
                'IDESTERNO' => mb_substr((string) $r->sku, 0, 20) ?: null,
            ], fn ($v) => $v !== null && $v !== ''))->values()->all();

            $righe[] = array_filter([
                'ARTICOLO' => mb_substr((string) $codArticolo, 0, 16),
                'UNITA' => mb_substr((string) $unita, 0, 3),
                'QUANTITA' => self::num($items->sum('quantita'), 0),
                'PREZZO' => self::num($prezzo, 2),
                'PERCENTUALEAGENTE' => self::num($ordine->agente?->commissione_perc ?? 0, 2),
                'NOTE' => null,
                'IDESTERNO' => "{$ordine->numero}-{$i}",
                'VARIANTI' => $varianti,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        }

        return array_filter([
            // testata
            'CLIENTE' => $ordine->cliente?->codice_cliente_erp,
            'AGENTE' => $ordine->agente?->codice_agente,
            'PAGAMENTO' => $pagamentoMap[$ordine->metodo_pagamento?->value] ?? null,
            'LISTINO' => $listinoMap[$ordine->listino_applicato?->value] ?? null,
            'STAGIONE' => self::stagioneComune($ordine),
            'DATAORDINE' => $ordine->data_ordine?->format('d-m-Y'),
            'NUMEROORDINE' => mb_substr((string) $ordine->numero, 0, 20),
            'NOTE' => mb_substr((string) $ordine->note_agente, 0, 200) ?: null,
            'SPESETRASPORTO' => self::num($ordine->spese_spedizione, 2),
            'VETTORE' => $def['vettore'] ?? null,
            'PORTO' => $def['porto'] ?? null,
            'DIVISA' => $def['divisa'] ?? null,
            'DICAMPIONARIO' => $def['dicampionario'] ?? 0,
            'IDESTERNO' => mb_substr((string) $ordine->idesterno, 0, 20),
            'RIGHE' => array_values($righe),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private static function num(int|float|string|null $v, int $decimals): string
    {
        return number_format((float) $v, $decimals, '.', '');
    }

    /** Se tutti i prodotti dell'ordine hanno la stessa stagione, restituisce il suo codice. */
    private static function stagioneComune(OrdineB2B $ordine): ?string
    {
        $codici = $ordine->righe
            ->map(fn ($r) => $r->variante?->prodotto?->stagione?->codice)
            ->filter()->unique();

        return $codici->count() === 1 ? mb_substr((string) $codici->first(), 0, 6) : null;
    }
}
