<?php

namespace App\Services\Erp\WinMino\Payload;

use App\Models\Destinazione;

/**
 * JSON per POST TDSClientiServerModule/AddDestinazione.
 * Chiave upsert: CODICE (6 char, gestita dal portale). Obbligatori: CODICE, NOME, CLIENTE.
 */
class DestinazionePayload
{
    public static function build(Destinazione $d): array
    {
        return array_filter([
            'CODICE' => $d->codice,
            'NOME' => mb_substr((string) $d->nome, 0, 50),
            'CLIENTE' => $d->cliente?->codice_cliente_erp,
            'INDIRIZZO' => mb_substr((string) $d->indirizzo, 0, 80) ?: null,
            'LOCALITA' => mb_substr((string) $d->localita, 0, 40) ?: null,
            'PROVINCIA' => $d->provincia,
            'CAP' => $d->cap,
            'TELEFONO' => $d->telefono,
            'CELLULARE' => $d->cellulare,
            'CODICEFISCALE' => $d->codice_fiscale,
            'PARTITAIVA' => $d->partita_iva,
            'E_MAIL' => $d->email,          // qui il campo ha l'underscore (vedi doc)
            'NOTE' => $d->note,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
