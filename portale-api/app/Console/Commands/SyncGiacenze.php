<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Sincronizza giacenze e prodotti verso i canali e-commerce (Shopify / WooCommerce).
 * STUB — implementazione reale nel pezzo 2c.
 */
class SyncGiacenze extends Command
{
    protected $signature = 'sync:giacenze {--channel=all : shopify|woocommerce|all}';

    protected $description = 'Push giacenze/prodotti verso i canali e-commerce';

    public function handle(): int
    {
        $this->info('sync:giacenze (stub) — canale: '.$this->option('channel'));

        // TODO (2c): iterare i prodotti attivi e delegare a ChannelManager.
        return self::SUCCESS;
    }
}
