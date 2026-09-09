<?php

namespace App\Services\Erp\WinMino;

/**
 * Decodifica il payload dei webservice GET WinMino nel formato { "meta": [...], "data": [...] }.
 *
 * Il blocco "meta" descrive i campi (nome, tipo, dimensione); "data" è la collezione
 * di record. A seconda del formato:
 *   - JSS (convenzionale): i record di "data" sono oggetti { CAMPO: valore }
 *   - JSO (ottimizzato):   i record di "data" sono array posizionali [v1, v2, ...]
 *
 * Questo decoder gestisce entrambi e restituisce sempre array<int, array<string,mixed>>
 * con chiavi = nomi campo così come esposti nel "meta".
 *
 * NOTA: la struttura esatta del "meta" non è nella documentazione — va verificata
 * su una risposta reale (docs/winmino-integration.md, punto aperto #1). Le euristiche
 * qui sotto coprono le forme più comuni; adeguare quando disponibile un campione.
 */
class MetaDecoder
{
    /** @return array<int, array<string,mixed>> */
    public static function decode(mixed $payload): array
    {
        $decoded = is_array($payload) ? $payload : json_decode((string) $payload, true);

        if (! is_array($decoded)) {
            return [];
        }

        // alcuni endpoint incapsulano in "result"
        $decoded = $decoded['result'] ?? $decoded;

        $data = $decoded['data'] ?? $decoded['Data'] ?? $decoded['DATA'] ?? null;
        if ($data === null) {
            // nessun wrapper: forse è già una lista di record
            return array_is_list($decoded) ? array_map(self::normalizeRow(...), $decoded) : [$decoded];
        }

        $fields = self::fieldNames($decoded['meta'] ?? $decoded['Meta'] ?? $decoded['META'] ?? []);

        $rows = [];
        foreach ((array) $data as $record) {
            if (is_array($record) && ! array_is_list($record)) {
                $rows[] = self::normalizeRow($record);          // già { campo: valore }
            } elseif (is_array($record) && $fields !== []) {
                $rows[] = self::normalizeRow(array_combine(
                    array_slice($fields, 0, count($record)),
                    array_slice($record, 0, count($fields)),
                ));
            } else {
                $rows[] = ['_' => $record];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private static function fieldNames(mixed $meta): array
    {
        $names = [];
        foreach ((array) $meta as $entry) {
            if (is_string($entry)) {
                $names[] = $entry;
            } elseif (is_array($entry)) {
                $names[] = (string) ($entry['name'] ?? $entry['Name'] ?? $entry['nome']
                    ?? $entry['field'] ?? $entry['Field'] ?? $entry['campo'] ?? '');
            }
        }

        return array_values(array_filter($names, fn ($n) => $n !== ''));
    }

    /** @return array<string,mixed> */
    private static function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[strtoupper((string) $k)] = $v;
        }

        return $out;
    }
}
