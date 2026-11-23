<?php

namespace App\Services\Erp\WinMino;

/**
 * Decodifica le risposte dei webservice GET WinMino (DataSnap).
 *
 * Forma reale (verificata su un WinMino, formato JSO):
 *
 *   {"result":[{"meta":[["NOME","ftString",50],["PREZZO","ftFloat"],…],
 *               "data":[["ROSSO","24,5"],["BLU",""],…]}]}
 *
 *  - "meta": elenco di [nome, tipo Delphi, dimensione?] nell'ordine delle colonne;
 *  - "data": record posizionali, tutti i valori sono STRINGHE; il vuoto è "" (= NULL);
 *    i decimali usano la VIRGOLA ("24,5").
 *
 * Il formato JSS restituisce invece record-oggetto: gestiti come passthrough.
 * Restituisce sempre array<int, array<string,mixed>> con chiavi MAIUSCOLE e valori
 * tipizzati (int / float / string; "" → null).
 */
class MetaDecoder
{
    /**
     * @param  callable(array<string,mixed>): bool|null  $keep  se dato, tiene solo i record per cui ritorna true
     *                                                          (evita di materializzare cataloghi da decine di migliaia di righe)
     * @return array<int, array<string,mixed>>
     */
    public static function decode(mixed $payload, ?callable $keep = null): array
    {
        $decoded = is_array($payload) ? $payload : json_decode((string) $payload, true);

        if (! is_array($decoded)) {
            return [];
        }

        $block = self::unwrap($decoded);
        $data = self::key($block, 'data');

        if ($data === null) {
            // nessun wrapper meta/data: forse è già una lista di record
            $rows = array_is_list($block) ? array_map(self::normalizeRow(...), $block) : [self::normalizeRow($block)];

            return $keep ? array_values(array_filter($rows, $keep)) : $rows;
        }

        $meta = self::meta(self::key($block, 'meta') ?? []);
        $names = array_column($meta, 0);
        $types = array_column($meta, 1);
        $n = count($names);

        $rows = [];
        foreach ((array) $data as $record) {
            if (! is_array($record)) {
                continue;
            }

            if (! array_is_list($record)) {
                $row = self::normalizeRow($record);            // JSS: già { campo: valore }
            } elseif ($n > 0) {
                $row = [];
                foreach ($names as $i => $name) {
                    $row[$name] = self::cast($record[$i] ?? null, $types[$i]);
                }
            } else {
                continue;
            }

            if ($keep === null || $keep($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** DataSnap incapsula in {"result":[{meta,data}]}: torna il blocco {meta,data}. */
    private static function unwrap(array $decoded): array
    {
        $block = $decoded['result'] ?? $decoded;

        if (is_array($block) && array_is_list($block) && isset($block[0]) && is_array($block[0])
            && (self::key($block[0], 'data') !== null || self::key($block[0], 'meta') !== null)) {
            return $block[0];
        }

        return is_array($block) ? $block : [];
    }

    private static function key(array $a, string $name): mixed
    {
        foreach ([$name, ucfirst($name), strtoupper($name)] as $k) {
            if (array_key_exists($k, $a)) {
                return $a[$k];
            }
        }

        return null;
    }

    /** @return list<array{0:string,1:string}> [nome MAIUSCOLO, tipo] */
    private static function meta(mixed $meta): array
    {
        $out = [];
        foreach ((array) $meta as $entry) {
            if (is_string($entry)) {
                $out[] = [strtoupper($entry), ''];
            } elseif (is_array($entry) && array_is_list($entry)) {
                $out[] = [strtoupper((string) ($entry[0] ?? '')), (string) ($entry[1] ?? '')];
            } elseif (is_array($entry)) {
                $name = $entry['name'] ?? $entry['Name'] ?? $entry['nome'] ?? $entry['field'] ?? $entry['Field'] ?? $entry['campo'] ?? '';
                $out[] = [strtoupper((string) $name), (string) ($entry['type'] ?? $entry['Type'] ?? '')];
            }
        }

        return array_values(array_filter($out, fn ($m) => $m[0] !== ''));
    }

    private static function cast(mixed $v, string $type): mixed
    {
        if ($v === null || $v === '') {
            return null;
        }

        return match (true) {
            in_array($type, ['ftFloat', 'ftCurrency', 'ftBCD', 'ftFMTBcd', 'ftExtended', 'ftSingle'], true) => (float) str_replace(',', '.', (string) $v),
            in_array($type, ['ftInteger', 'ftSmallint', 'ftLargeint', 'ftWord', 'ftAutoInc', 'ftShortint', 'ftByte'], true) => (int) $v,
            default => $v,
        };
    }

    /** @return array<string,mixed> */
    private static function normalizeRow(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            $out[strtoupper((string) $k)] = $v === '' ? null : $v;
        }

        return $out;
    }
}
