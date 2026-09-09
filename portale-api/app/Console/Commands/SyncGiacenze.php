<?php

namespace App\Console\Commands;

use App\Jobs\SyncProdottoEcommerce;
use App\Models\Prodotto;
use App\Services\Channels\ChannelManager;
use Illuminate\Console\Command;

/**
 * Push di prodotti/giacenze verso i canali e-commerce abilitati.
 *
 *   sync:giacenze                → giacenze di tutti i prodotti attivi (mode inventory)
 *   sync:giacenze --full         → anagrafica completa (pushProduct)
 *   sync:giacenze --channel=shopify
 *   sync:giacenze --codice=B003/02
 */
class SyncGiacenze extends Command
{
    protected $signature = 'sync:giacenze
        {--full : pubblica anagrafica completa invece delle sole giacenze}
        {--channel= : shopify|woocommerce (default: tutti gli abilitati)}
        {--codice=* : limita a uno o più codici prodotto}';

    protected $description = 'Push giacenze/prodotti verso i canali e-commerce';

    public function handle(ChannelManager $manager): int
    {
        $channel = $this->option('channel') ?: null;

        if ($manager->enabled()->isEmpty()) {
            $this->warn('Nessun canale e-commerce abilitato (Configurazioni Sistema). Skip.');

            return self::SUCCESS;
        }

        $mode = $this->option('full') ? 'full' : 'inventory';

        $query = Prodotto::query()->where('attivo', true);
        if ($codici = array_filter((array) $this->option('codice'))) {
            $query->whereIn('codice', $codici);
        }

        $n = 0;
        $query->select('id', 'codice')->chunk(200, function ($prodotti) use (&$n, $channel, $mode) {
            foreach ($prodotti as $p) {
                SyncProdottoEcommerce::dispatch($p->id, $channel, $mode);
                $n++;
            }
        });

        $this->info("{$n} prodotti accodati per la sincronizzazione ({$mode})".($channel ? " → {$channel}" : ''));

        return self::SUCCESS;
    }
}
