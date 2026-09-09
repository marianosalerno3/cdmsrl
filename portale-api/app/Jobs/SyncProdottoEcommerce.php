<?php

namespace App\Jobs;

use App\Models\Prodotto;
use App\Services\Channels\ChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push di un prodotto verso i canali e-commerce.
 *
 * mode:
 *   'full'      → pushProduct (anagrafica + varianti + immagini + giacenze)
 *   'inventory' → pushInventory (solo giacenze, più rapido)
 *
 * channel: null = tutti i canali abilitati; oppure 'shopify' | 'woocommerce'.
 */
class SyncProdottoEcommerce implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    public function __construct(
        public string $prodottoId,
        public ?string $channel = null,
        public string $mode = 'full',
    ) {}

    public function handle(ChannelManager $manager): void
    {
        $prodotto = Prodotto::with('varianti.taglia', 'varianti.colore', 'immagini', 'categoria', 'stagione', 'genere')
            ->find($this->prodottoId);

        if (! $prodotto) {
            return;
        }

        $channels = $this->channel
            ? collect([$manager->driver($this->channel)])->filter(fn ($c) => $manager->isEnabled($c->name()))
            : $manager->enabled();

        foreach ($channels as $channel) {
            $result = $this->mode === 'inventory'
                ? $channel->pushInventory($prodotto)
                : $channel->pushProduct($prodotto);

            logger()->{$result->success ? 'info' : 'warning'}('SyncProdottoEcommerce', [
                'prodotto' => $prodotto->codice,
                'channel' => $channel->name(),
                'mode' => $this->mode,
                'result' => $result->message,
            ]);
        }
    }
}
