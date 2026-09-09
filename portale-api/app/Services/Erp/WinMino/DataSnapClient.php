<?php

namespace App\Services\Erp\WinMino;

use App\Services\Erp\ErpException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP di basso livello per i webservice DataSnap di WinMino.
 *
 *   GET  http://<host>/datasnap/rest/<module>/<PREFIX>_<Function>[/Param=Value&...]
 *   POST http://<host>/datasnap/rest/<module>/<Function>   (body JSON)
 *
 * Auth: HTTP Basic (username/password comunicati da Magis). Da confermare per i POST.
 */
class DataSnapClient
{
    public function __construct(
        private readonly string $baseUrl,      // es. http://1.2.3.4:8080
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly int $timeout = 30,
        private readonly int $retries = 2,
        private readonly int $retrySleepMs = 800,
    ) {}

    /**
     * Chiamata GET a un webservice con prefisso di formato (JSO/JSS/XML).
     *
     * @param  array<string,scalar|null>  $params
     */
    public function get(string $module, string $function, array $params = [], string $prefix = 'JSO'): mixed
    {
        $path = "/datasnap/rest/{$module}/{$prefix}_{$function}";

        $segment = collect($params)
            ->reject(fn ($v) => $v === null || $v === '')
            ->map(fn ($v, $k) => "{$k}=".$this->formatParam($v))
            ->implode('&');

        if ($segment !== '') {
            $path .= '/'.$segment;
        }

        $response = $this->request()->get($this->baseUrl.$path);

        if ($response->failed()) {
            throw new ErpException("GET {$function} fallita: HTTP {$response->status()}", null, $response->body());
        }

        return $prefix === 'XML' ? $response->body() : $response->json();
    }

    /**
     * Chiamata POST (inserimento) — body JSON, risposta {"result":[...]}.
     *
     * @param  array<string,mixed>  $payload
     */
    public function post(string $module, string $function, array $payload): mixed
    {
        $url = $this->baseUrl."/datasnap/rest/{$module}/{$function}";

        $response = $this->request()->asJson()->post($url, $payload);

        if ($response->failed()) {
            throw new ErpException("POST {$function} fallita: HTTP {$response->status()}", null, $response->body());
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        $req = Http::timeout($this->timeout)
            ->retry($this->retries, $this->retrySleepMs)
            ->acceptJson();

        if ($this->username !== null && $this->username !== '') {
            $req = $req->withBasicAuth($this->username, (string) $this->password);
        }

        return $req;
    }

    private function formatParam(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d-m-Y');
        }

        return (string) $v;
    }
}
