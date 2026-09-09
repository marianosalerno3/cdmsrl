<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\OrdineB2B;
use App\Services\Channels\ChannelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Import ordini da Shopify (B2C) dentro ordini_b2b — tab "Ordini Shopify".
 * Chiave di dedup: shopify_order_id.
 */
class PullOrdiniEcommerce extends Command
{
    protected $signature = 'orders:pull-ecommerce
        {--since= : data ISO da cui importare (default: ultime 72h)}';

    protected $description = 'Import ordini da Shopify (B2C)';

    public function handle(ChannelManager $manager): int
    {
        if (! $manager->isEnabled('shopify')) {
            $this->warn('Shopify non configurato. Skip.');

            return self::SUCCESS;
        }

        $since = $this->option('since') ? Carbon::parse($this->option('since')) : now()->subHours(72);
        $shopify = $manager->driver('shopify');

        $tot = 0;
        foreach ($shopify->pullOrders($since) as $o) {
            OrdineB2B::updateOrCreate(
                ['shopify_order_id' => $o['external_id']],
                [
                    'numero' => 'EC-S-'.$o['numero'],
                    'cliente_nome' => $o['cliente_nome'] ?: 'Cliente e-commerce',
                    'cliente_email' => $o['cliente_email'],
                    'indirizzo_spedizione' => '—',
                    'stato' => OrderStatus::Ricevuto,
                    'subtotale' => $o['totale'],
                    'totale' => $o['totale'],
                    'data_ordine' => $o['data'] ? Carbon::parse($o['data'])->toDateString() : now()->toDateString(),
                    'ecommerce_payload' => $o,
                ],
            );
            $tot++;
        }

        $this->info("{$tot} ordini Shopify importati/aggiornati.");

        return self::SUCCESS;
    }
}
