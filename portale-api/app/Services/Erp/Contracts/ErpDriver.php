<?php

namespace App\Services\Erp\Contracts;

use App\Models\Cliente;
use App\Models\Destinazione;
use App\Models\OrdineB2B;
use App\Services\Erp\ErpResult;
use Carbon\CarbonInterface;

/**
 * Contratto verso un ERP. Implementazioni: WinMinoDriver, NullErpDriver.
 *
 * Le GET restituiscono liste di righe associative già decodificate dal blocco
 * "meta" della risposta (nomi campo = chiavi). La mappatura campo->modello è
 * responsabilità dei comandi di sync, non del driver.
 *
 * @phpstan-type Row array<string,mixed>
 */
interface ErpDriver
{
    public function name(): string;

    /** Verifica connettività / credenziali. */
    public function test(): bool;

    // --- scrittura (POST) ---

    public function upsertCliente(Cliente $cliente): ErpResult;

    public function upsertDestinazione(Destinazione $destinazione): ErpResult;

    public function pushOrdine(OrdineB2B $ordine): ErpResult;

    // --- lettura (GET) — array<int, Row> ---

    public function getClienti(?CarbonInterface $since = null): array;

    public function getDestinazioni(?CarbonInterface $since = null): array;

    public function getAgenti(?CarbonInterface $since = null): array;

    public function getArticoli(?CarbonInterface $since = null): array;

    /** Anagrafica completa articoli + giacenze da pubblicare (EC_GetGeneraleArticoliE). */
    public function getArticoliEcommerce(): array;

    public function getListiniPrezzi(?string $codListino = null): array;

    public function getGiacenze(?string $codArticolo = null, ?string $codColore = null): array;

    public function getColori(): array;

    public function getColoriArticoli(): array;

    public function getTaglieArticoli(): array;

    public function getGruppiTaglie(): array;

    public function getCategorie(): array;

    public function getStagioni(): array;

    public function getPagamenti(): array;

    public function getBarcodeTaglieColori(?string $codArticolo = null, ?string $codColore = null): array;

    public function getOrdiniClienti(CarbonInterface $daRegistrazione, CarbonInterface $aRegistrazione, ?CarbonInterface $daModifica = null, ?string $numeroOrdine = null): array;

    public function getProvvigioniAgenti(?CarbonInterface $da = null, ?CarbonInterface $a = null): array;

    /** @return array<int, array{nome:string}> nomi file immagine dell'articolo */
    public function getImmaginiArticolo(string $codArticolo): array;

    /** Contenuto immagine in base64, o null se assente. */
    public function getImmagineBase64(string $nomeImmagine): ?string;
}
