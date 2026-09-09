<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Colore;
use App\Models\Prodotto;
use App\Models\Stagione;
use App\Models\Taglia;
use App\Models\VarianteProdotto;
use App\Services\Erp\ErpManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Import prodotti/varianti/prezzi/giacenze da WinMino nel portale.
 * È il cuore dell'integrazione: WinMino → portale → (poi) Shopify B2C.
 *
 * ATTENZIONE: i nomi dei campi WinMino non sono nella documentazione (arrivano dal
 * blocco "meta" della risposta). Le mappe `pick(...)` qui sotto usano nomi candidati
 * e vanno confermate con risposte reali — vedi docs/winmino-integration.md (#1).
 */
class SyncProdotti extends Command
{
    protected $signature = 'sync:prodotti
        {--full : reimporta tutto ignorando la data di ultimo sync}
        {--push : dopo l\'import accoda il push dei prodotti verso i canali e-commerce}';

    protected $description = 'Import prodotti/varianti/prezzi/giacenze da WinMino (ERP)';

    public function handle(ErpManager $erp): int
    {
        if (! $erp->isConfigured()) {
            $this->warn('Nessun ERP configurato (Configurazioni Sistema → ERP). Skip.');

            return self::SUCCESS;
        }

        $driver = $erp->driver();

        $since = $this->option('full')
            ? null
            : optional(Prodotto::max('updated_at'), fn ($d) => Carbon::parse($d));

        // 1. anagrafiche di supporto (idempotenti, per codice)
        $this->line('Colori / taglie / categorie / stagioni…');
        $colori = $this->upsertColori($driver->getColori());
        $taglie = $this->upsertTaglie($driver->getGruppiTaglie());
        // categorie/stagioni si creano al volo dai prodotti se non presenti

        // 2. articoli + varianti (+ giacenze incluse in EC_GetGeneraleArticoliE)
        $articoli = $since ? $driver->getArticoli($since) : $driver->getArticoliEcommerce();
        $this->info(count($articoli).' articoli ricevuti da WinMino'.($since ? " (dal {$since->format('d-m-Y')})" : ''));

        // 3. prezzi per listino
        $prezziPerSku = $this->indicizzaPrezzi($driver->getListiniPrezzi());

        $ok = 0;
        foreach ($articoli as $row) {
            $codice = (string) $this->pick($row, ['CODARTICOLO', 'CODICE', 'ARTICOLO', 'COD']);
            if ($codice === '') {
                continue;
            }

            $prodotto = Prodotto::updateOrCreate(
                ['codice' => $codice],
                [
                    'nome' => $this->pick($row, ['DESCRIZIONE', 'NOME', 'DESART']) ?: $codice,
                    'descrizione' => $this->pick($row, ['DESCRIZIONEESTESA', 'NOTE', 'DESCRIZIONEWEB']),
                    'composizione' => $this->pick($row, ['COMPOSIZIONE']),
                    'tessuto' => $this->pick($row, ['TESSUTO']),
                    'tipo' => 'variabile',
                    'categoria_id' => $this->categoriaId($this->pick($row, ['CATEGORIA', 'DESCATEGORIA'])),
                    'stagione_id' => $this->stagioneId($this->pick($row, ['STAGIONE', 'CODSTAGIONE'])),
                    // 'attivo' NON toccato: è il flag "pubblica sul portale" gestito nel pannello
                ],
            );

            // varianti: righe colore/taglia dell'articolo
            $varRows = (array) ($row['VARIANTI'] ?? $row['varianti'] ?? []);
            foreach ($varRows as $v) {
                $sku = (string) $this->pick($v, ['SKU', 'BARCODE', 'CODVARIANTE']);
                if ($sku === '') {
                    continue;
                }
                $codColore = (string) $this->pick($v, ['CODCOLORE', 'COLORE']);
                $codTaglia = (string) $this->pick($v, ['CODTAGLIA', 'TAGLIA']);

                VarianteProdotto::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'prodotto_id' => $prodotto->id,
                        'colore_id' => $colori->get(strtoupper($codColore)) ?? $this->coloreIdByNome($this->pick($v, ['COLORE', 'DESCOLORE'])),
                        'taglia_id' => $taglie->get(strtoupper($codTaglia)) ?? $this->tagliaIdByNome($this->pick($v, ['TAGLIA', 'DESTAGLIA'])),
                        'prezzo' => $prezziPerSku[$sku] ?? $prezziPerSku[$codice] ?? (float) $this->pick($v, ['PREZZO', 'PREZZOLISTINO']) ?: 0,
                        'quantita' => (int) $this->pick($v, ['DISPONIBILE', 'GIACENZA', 'QTA', 'SURPLUS']),
                        'barcode' => $this->pick($v, ['BARCODE', 'EAN']),
                    ],
                );
            }
            $ok++;
        }

        $this->info("{$ok} prodotti sincronizzati.");

        if ($this->option('push')) {
            $this->call('sync:giacenze', ['--full' => true]);
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ helpers

    /** @return Collection<string,string> codice WinMino → id Colore */
    private function upsertColori(array $rows): Collection
    {
        $map = collect();
        foreach ($rows as $r) {
            $cod = (string) $this->pick($r, ['CODCOLORE', 'CODICE', 'COD']);
            $nome = $this->pick($r, ['DESCRIZIONE', 'NOME', 'DESCOLORE']) ?: $cod;
            if ($cod === '' && $nome === '') {
                continue;
            }
            $c = Colore::updateOrCreate(['nome' => strtoupper($nome)], ['codice' => $cod ?: null]);
            if ($cod !== '') {
                $map->put(strtoupper($cod), $c->id);
            }
        }

        return $map;
    }

    /** @return Collection<string,string> codice WinMino → id Taglia */
    private function upsertTaglie(array $rows): Collection
    {
        $map = collect();
        $i = 0;
        foreach ($rows as $r) {
            // GetGruppiTaglie può restituire un gruppo con array di taglie
            $taglie = (array) ($r['TAGLIE'] ?? $r['taglie'] ?? [$r]);
            foreach ($taglie as $t) {
                $cod = (string) $this->pick($t, ['CODTAGLIA', 'CODICE', 'COD', 'TAGLIA']);
                $nome = $this->pick($t, ['DESCRIZIONE', 'NOME', 'TAGLIA']) ?: $cod;
                if ($cod === '' && $nome === '') {
                    continue;
                }
                $x = Taglia::updateOrCreate(['nome' => strtoupper($nome)], ['codice' => $cod ?: null, 'ordine' => $i++]);
                if ($cod !== '') {
                    $map->put(strtoupper($cod), $x->id);
                }
            }
        }

        return $map;
    }

    /** @return array<string,float> sku|codArticolo → prezzo */
    private function indicizzaPrezzi(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $key = (string) $this->pick($r, ['SKU', 'BARCODE', 'CODARTICOLO', 'ARTICOLO']);
            $prezzo = (float) $this->pick($r, ['PREZZO', 'PREZZONETTO', 'PREZZOLISTINO']);
            if ($key !== '' && $prezzo > 0) {
                $out[$key] = $prezzo;
            }
        }

        return $out;
    }

    private function categoriaId(?string $nome): ?string
    {
        return $nome ? Categoria::firstOrCreate(['nome' => strtoupper($nome)])->id : null;
    }

    private function stagioneId(?string $codice): ?string
    {
        return $codice ? Stagione::firstOrCreate(['codice' => $codice], ['attiva' => true])->id : null;
    }

    private function coloreIdByNome(?string $nome): ?string
    {
        return $nome ? Colore::firstOrCreate(['nome' => strtoupper($nome)])->id : null;
    }

    private function tagliaIdByNome(?string $nome): ?string
    {
        return $nome ? Taglia::firstOrCreate(['nome' => strtoupper($nome)])->id : null;
    }

    /** @param array<string,mixed> $r @param list<string> $keys */
    private function pick(array $r, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== '') {
                return $r[$k];
            }
        }

        return null;
    }
}
