<?php

namespace App\Console\Commands;

use App\Models\Prodotto;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\ShopifyChannel;
use Illuminate\Console\Command;

/**
 * Rimette in ordine le taglie (42 → 60) dei prodotti già presenti su Shopify.
 * Serve una volta sola per i prodotti caricati prima dell'ordinamento automatico; si può rilanciare senza danni.
 *
 *   shopify:riordina-taglie
 *   shopify:riordina-taglie --codice=CG11880/24
 */
class RiordinaTaglieShopify extends Command
{
    protected $signature = 'shopify:riordina-taglie {--codice=* : limita a uno o più codici prodotto}';

    protected $description = 'Riordina le taglie (42 → 60) dei prodotti già presenti su Shopify';

    public function handle(ChannelManager $manager): int
    {
        if (! $manager->isEnabled('shopify')) {
            $this->warn('Shopify non è configurato (Configurazioni Sistema). Skip.');

            return self::SUCCESS;
        }

        $canale = $manager->driver('shopify');
        if (! $canale instanceof ShopifyChannel) {
            return self::FAILURE;
        }

        $query = Prodotto::whereNotNull('shopify_product_id')->with('varianti.taglia', 'varianti.colore');
        if ($codici = array_filter((array) $this->option('codice'))) {
            $query->whereIn('codice', $codici);
        }

        $ok = 0;
        $errori = 0;
        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->each(function (Prodotto $p) use ($canale, &$ok, &$errori, $bar) {
            $res = $canale->riordinaTaglie($p);
            if ($res->success) {
                $ok++;
            } else {
                $errori++;
                $this->newLine();
                $this->error("{$p->codice}: {$res->message}");
            }
            $bar->advance();
            usleep(300_000); // resta nei limiti di richieste di Shopify
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Taglie riordinate: {$ok} prodotti. Errori: {$errori}.");

        return $errori > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
