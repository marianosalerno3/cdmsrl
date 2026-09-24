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
use Illuminate\Support\Facades\DB;

/**
 * Import prodotti/varianti/prezzi/giacenze da WinMino nel portale.
 * WinMino → portale → (poi) Shopify B2C.
 *
 * Importa SOLO ciò che CDM sceglie di portare sul portale: le coppie LINEA:STAGIONE
 * di `config('winmino.import.selezioni')` (es. "CG:PE27" = Clara G, Primavera/Estate 2027).
 * Gli articoli senza prezzo nel listino base (`winmino.import.listino_base`) vengono esclusi.
 *
 * Campi WinMino verificati su risposte reali (GET, formato JSO):
 *  - GetArticoli:            CODICE, DESCRIZIONE, COMPOSIZIONE, CODLINEA, CODSTAGIONE,
 *                            CODGRUPPOMERCEOLOGICO, CODPACCHETTO, UM, DESCRIZIONEWEB
 *  - GetListiniPrezzi:       CODLISTINO, CODARTICOLO, PREZZO, SCONTO1, SCONTO2
 *  - GetBarcodeTaglieColori: CODARTICOLO, CODCOLORE, TAGLIA, BARCODE   (una riga per variante)
 *  - GetGiacenze:            CODDEPOSITO, CODARTICOLO, CODCOLORE, TAGLIA, ESISTENZA, IMPEGNATA
 * I codici articolo contengono "/": DataSnapClient li URL-encoda.
 */
class SyncProdotti extends Command
{
    protected $signature = 'sync:prodotti
        {--push : dopo l\'import accoda il push dei prodotti verso i canali e-commerce}
        {--dry-run : legge da WinMino e mostra cosa importerebbe, senza scrivere nel DB}
        {--limit= : limita a N articoli (per prove)}';

    protected $description = 'Import prodotti/varianti/prezzi/giacenze da WinMino (solo le selezioni configurate)';

    public function handle(ErpManager $erp): int
    {
        ini_set('memory_limit', '1G');

        if (! $erp->isConfigured()) {
            $this->warn('Nessun ERP configurato (Configurazioni Sistema → ERP). Skip.');

            return self::SUCCESS;
        }

        $selezioni = $this->selezioni();
        if ($selezioni === []) {
            $this->warn('Nessuna selezione da importare (WINMINO_IMPORT_SELEZIONI, es. "CG:PE27"). Skip.');

            return self::SUCCESS;
        }

        $listino = (string) config('winmino.import.listino_base');
        $depositi = array_map('strtoupper', (array) config('winmino.import.depositi', []));
        $dry = (bool) $this->option('dry-run');
        $driver = $erp->driver();

        $this->info('Selezioni: '.implode(', ', array_map(fn ($s) => "{$s[0]}:{$s[1]}", $selezioni))." · listino base: {$listino}".($dry ? ' · DRY-RUN' : ''));

        // 1. anagrafiche di supporto (nomi leggibili)
        $stagioni = $this->mappa($driver->getStagioni(), 'CODSTAGIONE', 'NOMESTAGIONE');
        $gruppi = $this->mappa($driver->getGruppiMerceologici(), 'CODICE', 'NOME');
        $pacchetti = $this->mappa($driver->getPacchetti(), 'CODICE', 'NOME');
        $coloriWm = collect($driver->getColori())->keyBy(fn ($r) => (string) $r['CODICE']);

        // 2. articoli delle selezioni (filtrati in fase di decodifica: GetArticoli ha ~25k righe)
        $set = collect($selezioni)->mapWithKeys(fn ($s) => ["{$s[0]}|{$s[1]}" => true])->all();
        $articoli = $driver->getArticoli(null, fn (array $r) => isset($set[($r['CODLINEA'] ?? '').'|'.($r['CODSTAGIONE'] ?? '')]));
        $this->info(count($articoli).' articoli nelle selezioni');

        // 3. prezzi dal listino base
        $prezzi = [];
        foreach ($driver->getListiniPrezzi($listino) as $r) {
            if (($r['CODLISTINO'] ?? null) === $listino && ($r['PREZZO'] ?? 0) > 0) {
                $prezzi[(string) $r['CODARTICOLO']] = round((float) $r['PREZZO'], 2);
            }
        }

        $conPrezzo = array_values(array_filter($articoli, fn ($a) => isset($prezzi[$a['CODICE']])));
        $senzaPrezzo = array_values(array_filter($articoli, fn ($a) => ! isset($prezzi[$a['CODICE']])));
        if ($senzaPrezzo !== []) {
            $this->warn(count($senzaPrezzo)." articoli senza prezzo nel listino {$listino}: ESCLUSI");
            $this->line('  '.implode(', ', array_column($senzaPrezzo, 'CODICE')));
        }

        if ($limit = (int) $this->option('limit')) {
            $conPrezzo = array_slice($conPrezzo, 0, $limit);
        }

        if ($dry) {
            $this->info(count($conPrezzo).' articoli verrebbero importati. Nessuna scrittura (dry-run).');

            return self::SUCCESS;
        }

        // 4. import per articolo: varianti (barcode) + giacenze
        $stat = ['prodotti' => 0, 'varianti' => 0, 'errori' => 0, 'senza_varianti' => 0, 'pezzi' => 0];
        $depositiVisti = [];
        $bar = $this->output->createProgressBar(count($conPrezzo));
        $bar->start();

        foreach ($conPrezzo as $a) {
            $codice = (string) $a['CODICE'];

            try {
                $varianti = $driver->getBarcodeTaglieColori($codice);
                $giacenze = $this->giacenze($driver->getGiacenze($codice), $depositi, $depositiVisti);

                DB::transaction(function () use ($a, $codice, $varianti, $giacenze, $prezzi, $stagioni, $gruppi, $pacchetti, $coloriWm, &$stat) {
                    $prodotto = Prodotto::updateOrCreate(['codice' => $codice], [
                        'nome' => $a['DESCRIZIONE'] ?? $codice,
                        'descrizione' => $a['DESCRIZIONEWEB'] ?? null,
                        'composizione' => isset($a['COMPOSIZIONE']) ? trim((string) $a['COMPOSIZIONE']) : null,
                        'pacchetto' => $pacchetti[$a['CODPACCHETTO'] ?? ''] ?? null,
                        'tipo' => 'variabile',
                        'unita' => $a['UM'] ?? null,
                        'categoria_id' => $this->categoriaId($gruppi[$a['CODGRUPPOMERCEOLOGICO'] ?? ''] ?? null),
                        'stagione_id' => $this->stagioneId($a['CODSTAGIONE'] ?? null, $stagioni),
                        'prezzo_base' => $prezzi[$codice],
                        // 'attivo' NON toccato: è il flag "pubblica sul portale" gestito dal pannello
                    ]);

                    if ($varianti === []) {
                        $stat['senza_varianti']++;
                    }

                    foreach ($varianti as $v) {
                        $codColore = (string) ($v['CODCOLORE'] ?? '');
                        $taglia = (string) ($v['TAGLIA'] ?? '');
                        $sku = (string) ($v['BARCODE'] ?? '') ?: "{$codice}|{$codColore}|{$taglia}";
                        $qta = $giacenze[$codColore.'|'.$taglia] ?? 0;   // assente = 0 (WinMino non restituisce l'esaurito)

                        VarianteProdotto::withoutEvents(fn () => VarianteProdotto::updateOrCreate(['sku' => $sku], [
                            'prodotto_id' => $prodotto->id,
                            'colore_id' => $this->coloreId($codColore, $coloriWm),
                            'taglia_id' => $this->tagliaId($taglia),
                            'prezzo' => $prezzi[$codice],
                            'quantita' => $qta,
                            'barcode' => $v['BARCODE'] ?? null,
                        ]));

                        $stat['varianti']++;
                        $stat['pezzi'] += $qta;
                    }

                    $stat['prodotti']++;
                });
            } catch (\Throwable $e) {
                $stat['errori']++;
                $this->newLine();
                $this->error("{$codice}: ".strtok($e->getMessage(), "\n"));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(['Prodotti', 'Varianti', 'Pezzi disponibili', 'Senza varianti', 'Errori'], [[
            $stat['prodotti'], $stat['varianti'], $stat['pezzi'], $stat['senza_varianti'], $stat['errori'],
        ]]);
        if ($depositiVisti !== []) {
            arsort($depositiVisti);
            $this->line('Depositi in GetGiacenze (disponibile = esistenza − impegnata): '
                .collect($depositiVisti)->map(fn ($q, $d) => "{$d}={$q}")->implode(', ')
                .($depositi ? ' · sommati: '.implode(',', $depositi) : ' · sommati: TUTTI'));
        }

        if ($this->option('push')) {
            $this->call('sync:giacenze', ['--full' => true]);
        }

        return $stat['errori'] > 0 && $stat['prodotti'] === 0 ? self::FAILURE : self::SUCCESS;
    }

    // ------------------------------------------------------------------ helpers

    /** @return list<array{0:string,1:string}> [linea, stagione] */
    private function selezioni(): array
    {
        $out = [];
        foreach ((array) config('winmino.import.selezioni', []) as $s) {
            [$linea, $stagione] = array_pad(array_map('trim', explode(':', (string) $s, 2)), 2, '');
            if ($linea !== '' && $stagione !== '') {
                $out[] = [$linea, $stagione];
            }
        }

        return $out;
    }

    /** @return array<string,string> */
    private function mappa(array $rows, string $chiave, string $valore): array
    {
        $m = [];
        foreach ($rows as $r) {
            if (isset($r[$chiave])) {
                $m[(string) $r[$chiave]] = (string) ($r[$valore] ?? $r[$chiave]);
            }
        }

        return $m;
    }

    /**
     * Giacenza netta per variante: somma (ESISTENZA − IMPEGNATA) sui depositi scelti, mai negativa.
     *
     * @param  list<string>  $depositi  vuoto = tutti
     * @param  array<string,int>  $visti  (in/out) totale per deposito, per il resoconto
     * @return array<string,int> "CODCOLORE|TAGLIA" → quantità
     */
    private function giacenze(array $rows, array $depositi, array &$visti): array
    {
        $out = [];
        foreach ($rows as $r) {
            $dep = strtoupper((string) ($r['CODDEPOSITO'] ?? ''));
            $netto = (float) ($r['ESISTENZA'] ?? 0) - (float) ($r['IMPEGNATA'] ?? 0);
            $visti[$dep] = ($visti[$dep] ?? 0) + (int) round($netto);

            if ($depositi !== [] && ! in_array($dep, $depositi, true)) {
                continue;
            }
            $k = ($r['CODCOLORE'] ?? '').'|'.($r['TAGLIA'] ?? '');
            $out[$k] = ($out[$k] ?? 0) + $netto;
        }

        return array_map(fn ($q) => max(0, (int) floor($q)), $out);
    }

    private function categoriaId(?string $nome): ?string
    {
        return $nome ? Categoria::firstOrCreate(['nome' => $nome])->id : null;
    }

    private function stagioneId(?string $codice, array $nomi): ?string
    {
        if (! $codice) {
            return null;
        }
        $s = Stagione::firstOrCreate(['codice' => $codice], ['nome' => $nomi[$codice] ?? null, 'attiva' => true]);
        if ($s->nome === null && isset($nomi[$codice])) {
            $s->update(['nome' => $nomi[$codice]]);
        }

        return $s->id;
    }

    private function coloreId(string $codice, $coloriWm): ?string
    {
        if ($codice === '') {
            return null;
        }
        $wm = $coloriWm->get($codice);
        $nome = strtoupper((string) ($wm['NOME'] ?? $codice));
        $attrs = ['codice' => $codice];
        if (isset($wm['COLOREWEB']) && preg_match('/^#?[0-9A-Fa-f]{6}$/', (string) $wm['COLOREWEB'])) {
            $attrs['hex'] = '#'.strtoupper(ltrim((string) $wm['COLOREWEB'], '#'));
        }

        return Colore::updateOrCreate(['nome' => $nome], $attrs)->id;
    }

    private function tagliaId(string $nome): ?string
    {
        $nome = strtoupper(trim($nome));

        return $nome === '' ? null : Taglia::firstOrCreate(['nome' => $nome], ['codice' => $nome, 'ordine' => $this->ordineTaglia($nome)])->id;
    }

    /** Numeriche in ordine numerico (42, 44…), poi XS…XXL, poi taglia unica. */
    private function ordineTaglia(string $nome): int
    {
        if (is_numeric($nome)) {
            return (int) $nome;
        }

        return ['XXS' => 1000, 'XS' => 1001, 'S' => 1002, 'M' => 1003, 'L' => 1004, 'XL' => 1005, 'XXL' => 1006, 'XXXL' => 1007,
            'UNICA' => 2000, 'UN' => 2000, 'TU' => 2000][$nome] ?? 3000;
    }
}
