<?php

namespace App\Services\Erp;

/**
 * Esito di una chiamata POST WinMino.
 * Le POST rispondono con {"result":[{"<codice>":"<messaggio>"}, ...]}.
 *   - successo: codice "0" (ordini/destinazioni) oppure il codice cliente (AddCliente)
 *   - errore: codice negativo -1..-7 (vedi docs/winmino-integration.md)
 */
class ErpResult
{
    private const ERROR_MESSAGES = [
        -1 => 'Valore non specificato',
        -2 => 'Valore specificato inesistente',
        -3 => 'Errore di query sul database',
        -4 => 'Valore obbligatorio non specificato',
        -5 => 'Errore in fase di memorizzazione',
        -6 => 'La chiave RIGHE non è presente o non è un array',
        -7 => 'La chiave VARIANTI non è un array',
    ];

    /** @param array<int,array{code:string,message:string}> $entries */
    private function __construct(
        public readonly bool $success,
        public readonly ?string $code,
        public readonly string $message,
        public readonly array $entries,
        public readonly mixed $raw,
    ) {}

    public static function fromResponse(mixed $body): self
    {
        $decoded = is_array($body) ? $body : json_decode((string) $body, true);
        $rows = $decoded['result'] ?? [];

        $entries = [];
        foreach ((array) $rows as $row) {
            foreach ((array) $row as $code => $message) {
                $entries[] = ['code' => (string) $code, 'message' => (string) $message];
            }
        }

        $first = $entries[0] ?? ['code' => null, 'message' => 'Risposta ERP non riconosciuta'];
        $code = $first['code'];
        $isError = $code !== null && is_numeric($code) && (int) $code < 0;

        $message = $isError
            ? sprintf('[%s] %s — %s', $code, self::ERROR_MESSAGES[(int) $code] ?? 'Errore', $first['message'])
            : $first['message'];

        return new self(
            success: ! $isError && $code !== null,
            code: $code,
            message: $message,
            entries: $entries,
            raw: $decoded,
        );
    }

    public function throwIfFailed(): self
    {
        if (! $this->success) {
            throw new ErpException($this->message, is_numeric($this->code) ? (int) $this->code : null, $this->raw);
        }

        return $this;
    }
}
