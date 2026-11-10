<?php

namespace App\Services\Erp\WinMino\Payload;

use App\Models\Cliente;

/**
 * Costruisce il JSON per POST TDSClientiServerModule/AddCliente.
 * Chiave upsert: CODICEFISCALE. Obbligatori: CODICEFISCALE, RAGIONESOCIALE.
 */
class ClientePayload
{
    public static function build(Cliente $c): array
    {
        $listinoMap = config('winmino.listino_map', []);
        $pagamentoMap = config('winmino.pagamento_map', []);

        $codiceFe = $c->pec ?: $c->codice_sdi;
        $tipoCodiceFe = $c->tipologia_codice_fe ?? ($c->pec ? 'P' : ($c->codice_sdi ? 'C' : null));

        return array_filter([
            // obbligatori
            'CODICEFISCALE' => $c->codice_fiscale ?: $c->partita_iva,
            'RAGIONESOCIALE' => mb_substr((string) $c->denominazione, 0, 50),

            // commerciale
            'PARTITAIVA' => $c->partita_iva,
            'AGENTE' => $c->agente?->codice_agente,
            'LISTINO' => $listinoMap[$c->listinoEffettivo()->value] ?? null,
            'PAGAMENTO' => null, // valorizzato solo se noto il default cliente
            'CRITERIOSCONTO' => $c->criterio_sconto ?? config('winmino.criterio_sconto_default', 'N'),
            'ZONA' => $c->zona,
            'NAZIONE' => $c->nazione_erp,

            // fatturazione elettronica
            'TIPOLOGIAFE' => $c->tipologia_fe,
            'TIPOLOGIACODICEFE' => $tipoCodiceFe,
            'CODICEDESTINATARIOFE' => $codiceFe,

            // indirizzo / contatti
            'INDIRIZZO' => mb_substr((string) $c->indirizzo, 0, 40) ?: null,
            'LOCALITA' => mb_substr((string) $c->citta, 0, 40) ?: null,
            'PROVINCIA' => $c->provincia,
            'CAP' => $c->cap,
            'TELEFONO' => $c->telefono,
            'EMAIL' => $c->email,

            // persona fisica
            'NOMEPERSONAFISICA' => $c->nome,
            'COGNOMEPERSONAFISICA' => $c->cognome,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
