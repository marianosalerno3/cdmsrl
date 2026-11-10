<?php

namespace App\Jobs;

use App\Models\Prodotto;
use App\Services\Channels\ChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
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
 * channel: null = tutti i canali abilitati; oppure 'shopify'.
 *
 * Unico per prodotto+canale+modo finche' non parte: un import salva decine di varianti dello stesso prodotto
 * e senza questo accoderebbe un job per ogni salvataggio (la coda arrivava a decine di migliaia di job).
 */
class SyncProdottoEcommerce implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    /** Se un job resta bloccato, dopo 1h puo' essere riaccodato. */
    public int $uniqueFor = 3600;

    /** Push con immagini + throttling Shopify (2 chiamate/s): il default di 60s va stretto. */
    public int $timeout = 240;

    public function __construct(
        public string $prodottoId,
        public ?string $channel = null,
        public string $mode = 'full',
    ) {}

    public function uniqueId(): string
    {
        return "{$this->prodottoId}:".($this->channel ?? 'all').":{$this->mode}";
    }

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
