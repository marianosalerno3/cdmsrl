<?php

namespace App\Jobs;

use App\Models\Prodotto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push di un prodotto (+ varianti + immagini + giacenze) verso un canale e-commerce.
 *
 * STUB — l'implementazione reale arriva nel pezzo 2c (integrazioni):
 *   App\Services\Channels\ShopifyChannel / WooCommerceChannel.
 */
class SyncProdottoEcommerce implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $prodottoId,
        public string $channel, // 'shopify' | 'woocommerce'
    ) {}

    public function handle(): void
    {
        $prodotto = Prodotto::with('varianti', 'immagini')->find($this->prodottoId);

        if (! $prodotto) {
            return;
        }

        // TODO (2c): app(ChannelManager::class)->driver($this->channel)->pushProduct($prodotto);
        logger()->info('SyncProdottoEcommerce (stub)', [
            'prodotto' => $prodotto->codice,
            'channel' => $this->channel,
        ]);
    }
}
