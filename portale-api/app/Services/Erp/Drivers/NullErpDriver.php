<?php

namespace App\Services\Erp\Drivers;

use App\Models\Cliente;
use App\Models\Destinazione;
use App\Models\OrdineB2B;
use App\Services\Erp\Contracts\ErpDriver;
use App\Services\Erp\ErpResult;
use Carbon\CarbonInterface;

/**
 * Driver inattivo: usato quando nessun ERP è configurato (erp_driver = "null").
 * Le scritture restituiscono un esito "no-op" di successo; le letture liste vuote.
 */
class NullErpDriver implements ErpDriver
{
    public function name(): string
    {
        return 'null';
    }

    public function test(): bool
    {
        return false;
    }

    public function upsertCliente(Cliente $cliente): ErpResult
    {
        return $this->noop();
    }

    public function upsertDestinazione(Destinazione $destinazione): ErpResult
    {
        return $this->noop();
    }

    public function pushOrdine(OrdineB2B $ordine): ErpResult
    {
        return $this->noop();
    }

    public function getClienti(?CarbonInterface $since = null): array
    {
        return [];
    }

    public function getDestinazioni(?CarbonInterface $since = null): array
    {
        return [];
    }

    public function getAgenti(?CarbonInterface $since = null): array
    {
        return [];
    }

    public function getArticoli(?CarbonInterface $since = null): array
    {
        return [];
    }

    public function getArticoliEcommerce(): array
    {
        return [];
    }

    public function getListiniPrezzi(?string $codListino = null): array
    {
        return [];
    }

    public function getGiacenze(?string $codArticolo = null, ?string $codColore = null): array
    {
        return [];
    }

    public function getColori(): array
    {
        return [];
    }

    public function getColoriArticoli(): array
    {
        return [];
    }

    public function getTaglieArticoli(): array
    {
        return [];
    }

    public function getGruppiTaglie(): array
    {
        return [];
    }

    public function getCategorie(): array
    {
        return [];
    }

    public function getStagioni(): array
    {
        return [];
    }

    public function getPagamenti(): array
    {
        return [];
    }

    public function getBarcodeTaglieColori(?string $codArticolo = null, ?string $codColore = null): array
    {
        return [];
    }

    public function getOrdiniClienti(CarbonInterface $daRegistrazione, CarbonInterface $aRegistrazione, ?CarbonInterface $daModifica = null, ?string $numeroOrdine = null): array
    {
        return [];
    }

    public function getProvvigioniAgenti(?CarbonInterface $da = null, ?CarbonInterface $a = null): array
    {
        return [];
    }

    public function getImmaginiArticolo(string $codArticolo): array
    {
        return [];
    }

    public function getImmagineBase64(string $nomeImmagine): ?string
    {
        return null;
    }

    private function noop(): ErpResult
    {
        return ErpResult::fromResponse(['result' => [['0' => 'NOOP: nessun ERP configurato']]]);
    }
}
