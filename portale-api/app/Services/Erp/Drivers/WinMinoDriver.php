<?php

namespace App\Services\Erp\Drivers;

use App\Models\Cliente;
use App\Models\Destinazione;
use App\Models\OrdineB2B;
use App\Services\Erp\Contracts\ErpDriver;
use App\Services\Erp\ErpException;
use App\Services\Erp\ErpResult;
use App\Services\Erp\WinMino\DataSnapClient;
use App\Services\Erp\WinMino\MetaDecoder;
use App\Services\Erp\WinMino\Payload\ClientePayload;
use App\Services\Erp\WinMino\Payload\DestinazionePayload;
use App\Services\Erp\WinMino\Payload\OrdinePayload;
use Carbon\CarbonInterface;

class WinMinoDriver implements ErpDriver
{
    private string $moduleGet;

    private string $moduleClienti;

    private string $moduleOrdini;

    private string $prefix;

    public function __construct(private readonly DataSnapClient $client)
    {
        $this->moduleGet = config('winmino.modules.get', 'TsmStandard');
        $this->moduleClienti = config('winmino.modules.clienti', 'TDSClientiServerModule');
        $this->moduleOrdini = config('winmino.modules.ordini', 'TDSOrdiniCLienti');
        $this->prefix = config('winmino.get_prefix', 'JSO');
    }

    public function name(): string
    {
        return 'winmino';
    }

    private ?string $lastError = null;

    public function test(): bool
    {
        $this->lastError = null;

        try {
            $this->client->get($this->moduleGet, 'GetPagamenti', [], $this->prefix);

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $this->describeFailure($e);

            return false;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** Messaggio leggibile, senza mai includere credenziali. */
    private function describeFailure(\Throwable $e): string
    {
        $status = match (true) {
            $e instanceof \Illuminate\Http\Client\RequestException => $e->response->status(),
            // DataSnapClient segnala l'errore HTTP come "GET <fn> fallita: HTTP <codice>"
            $e instanceof ErpException && preg_match('/HTTP (\d{3})/', $e->getMessage(), $m) === 1 => (int) $m[1],
            default => null,
        };

        if ($status !== null) {
            return match (true) {
                $status === 401 => 'HTTP 401: utente/password rifiutati dal server WinMino.',
                $status === 403 => 'HTTP 403: accesso negato (utente non abilitato ai webservice?).',
                $status === 404 => "HTTP 404: modulo '{$this->moduleGet}' o funzione non trovati.",
                default => "HTTP {$status} dal server WinMino.",
            };
        }

        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'Server non raggiungibile (timeout o connessione rifiutata): '.strtok($e->getMessage(), "\n");
        }

        return get_class($e).': '.$e->getMessage();
    }

    // ---------------------------------------------------------------- POST

    public function upsertCliente(Cliente $cliente): ErpResult
    {
        $res = ErpResult::fromResponse(
            $this->client->post($this->moduleClienti, 'AddCliente', ClientePayload::build($cliente)),
        );

        // AddCliente ritorna il codice cliente WinMino in caso di successo
        if ($res->success && $res->code !== null && $res->code !== '0') {
            $cliente->forceFill([
                'codice_cliente_erp' => $res->code,
                'sincronizzato_erp_at' => now(),
            ])->saveQuietly();
        }

        return $res;
    }

    public function upsertDestinazione(Destinazione $destinazione): ErpResult
    {
        $res = ErpResult::fromResponse(
            $this->client->post($this->moduleClienti, 'AddDestinazione', DestinazionePayload::build($destinazione)),
        );

        if ($res->success) {
            $destinazione->forceFill(['sincronizzato_erp_at' => now()])->saveQuietly();
        }

        return $res;
    }

    public function pushOrdine(OrdineB2B $ordine): ErpResult
    {
        $payload = OrdinePayload::build($ordine);

        if (blank($ordine->cliente?->codice_cliente_erp)) {
            throw new ErpException('Cliente senza codice WinMino: sincronizzare prima il cliente.');
        }

        $res = ErpResult::fromResponse(
            $this->client->post($this->moduleOrdini, 'AddOrdineCliente', $payload),
        );

        $ordine->forceFill([
            'erp_response' => $res->raw,
            'inviato_erp_at' => $res->success ? now() : $ordine->inviato_erp_at,
        ])->saveQuietly();

        return $res;
    }

    // ---------------------------------------------------------------- GET

    public function getClienti(?CarbonInterface $since = null): array
    {
        return $this->getList('GetClienti', $since ? ['DaData' => $since->format('d-m-Y')] : []);
    }

    public function getDestinazioni(?CarbonInterface $since = null): array
    {
        return $this->getList('GetDestinazioniDiverseClienti', $since ? ['DaData' => $since->format('d-m-Y')] : []);
    }

    public function getAgenti(?CarbonInterface $since = null): array
    {
        return $this->getList('GetAgenti', $since ? ['DaData' => $since->format('d-m-Y')] : []);
    }

    public function getArticoli(?CarbonInterface $since = null, ?callable $keep = null): array
    {
        return $this->getList('GetArticoli', $since ? ['DaData' => $since->format('d-m-Y')] : [], $keep);
    }

    public function getArticoliEcommerce(): array
    {
        return $this->getList('EC_GetGeneraleArticoliE');
    }

    public function getListiniPrezzi(?string $codListino = null): array
    {
        return $this->getList('GetListiniPrezzi', array_filter(['CodListino' => $codListino]));
    }

    public function getGiacenze(?string $codArticolo = null, ?string $codColore = null): array
    {
        return $this->getList('GetGiacenze', array_filter([
            'CodArticolo' => $codArticolo,
            'CodColore' => $codColore,
        ]));
    }

    public function getColori(): array
    {
        return $this->getList('GetListaColori');
    }

    public function getColoriArticoli(): array
    {
        return $this->getList('GetColoriArticoli');
    }

    public function getTaglieArticoli(): array
    {
        return $this->getList('GetTaglieArticoli');
    }

    public function getGruppiTaglie(): array
    {
        return $this->getList('GetGruppiTaglie');
    }

    public function getCategorie(): array
    {
        return $this->getList('GetCategorieArticoli');
    }

    public function getStagioni(): array
    {
        return $this->getList('GetStagioni');
    }

    public function getGruppiMerceologici(): array
    {
        return $this->getList('GetGruppiMerceologici');
    }

    public function getPacchetti(): array
    {
        return $this->getList('GetPacchetti');
    }

    public function getPagamenti(): array
    {
        return $this->getList('GetPagamenti');
    }

    public function getBarcodeTaglieColori(?string $codArticolo = null, ?string $codColore = null): array
    {
        return $this->getList('GetBarcodeTaglieColori', array_filter([
            'CodArticolo' => $codArticolo,
            'CodColore' => $codColore,
        ]));
    }

    public function getOrdiniClienti(CarbonInterface $daRegistrazione, CarbonInterface $aRegistrazione, ?CarbonInterface $daModifica = null, ?string $numeroOrdine = null): array
    {
        return $this->getList('GetOrdiniclienti', array_filter([
            'DaDataModifica' => ($daModifica ?? $daRegistrazione)->format('d-m-Y'),
            'DaDataRegistrazione' => $daRegistrazione->format('d-m-Y'),
            'ADataRegistrazione' => $aRegistrazione->format('d-m-Y'),
            'NumeroOrdine' => $numeroOrdine,
        ]));
    }

    public function getProvvigioniAgenti(?CarbonInterface $da = null, ?CarbonInterface $a = null): array
    {
        return $this->getList('GetProvvigioniAgentiDaDocumenti', array_filter([
            'DaDataRegistrazione' => $da?->format('d-m-Y'),
            'ADataRegistrazione' => $a?->format('d-m-Y'),
        ]));
    }

    public function getImmaginiArticolo(string $codArticolo): array
    {
        return $this->getList('GetListaImmaginiArticolo', ['CodArticolo' => $codArticolo]);
    }

    public function getImmagineBase64(string $nomeImmagine): ?string
    {
        // endpoint senza prefisso di formato
        $body = $this->client->get($this->moduleGet, 'GetImmagineBase64', ['NomeImmagine' => $nomeImmagine], '');

        if (is_array($body)) {
            return $body['result'] ?? $body['immagine'] ?? $body['data'] ?? null;
        }

        return is_string($body) ? $body : null;
    }

    /**
     * @param  array<string,scalar>  $params
     * @return array<int, array<string,mixed>>
     */
    private function getList(string $function, array $params = [], ?callable $keep = null): array
    {
        return MetaDecoder::decode(
            $this->client->get($this->moduleGet, $function, $params, $this->prefix),
            $keep,
        );
    }
}
