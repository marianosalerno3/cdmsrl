<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\OrdineB2B;
use App\Services\Channels\ChannelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Import ordini dai canali e-commerce dentro ordini_b2b (tab "Ordini Shopify"/"Ordini B2B").
 * Chiave di dedup: shopify_order_id / woocommerce_order_id.
 */
class PullOrdiniEcommerce extends Command
{
    protected $signature = 'orders:pull-ecommerce
        {--channel= : shopify|woocommerce (default: tutti gli abilitati)}
        {--since= : data ISO da cui importare (default: ultime 72h)}';

    protected $description = 'Import ordini dai canali e-commerce';

    public function handle(ChannelManager $manager): int
    {
        $since = $this->option('since') ? Carbon::parse($this->option('since')) : now()->subHours(72);

        $channels = $this->option('channel')
            ? collect([$manager->driver($this->option('channel'))])
            : $manager->enabled();

        if ($channels->isEmpty()) {
            $this->warn('Nessun canale e-commerce abilitato. Skip.');

            return self::SUCCESS;
        }

        $tot = 0;
        foreach ($channels as $channel) {
            $col = $channel->name() === 'shopify' ? 'shopify_order_id' : 'woocommerce_order_id';

            foreach ($channel->pullOrders($since) as $o) {
                OrdineB2B::updateOrCreate(
                    [$col => $o['external_id']],
                    [
                        'numero' => 'EC-'.$channel->name()[0].'-'.$o['numero'],
                        'cliente_nome' => $o['cliente_nome'] ?: 'Cliente e-commerce',
                        'cliente_email' => $o['cliente_email'],
                        'indirizzo_spedizione' => '—',
                        'stato' => OrderStatus::Ricevuto,
                        'subtotale' => $o['totale'],
                        'totale' => $o['totale'],
                        'data_ordine' => $o['data'] ? Carbon::parse($o['data'])->toDateString() : now()->toDateString(),
                        'wordpress_payload' => $o,
                    ],
                );
                $tot++;
            }
        }

        $this->info("{$tot} ordini e-commerce importati/aggiornati.");

        return self::SUCCESS;
    }
}
