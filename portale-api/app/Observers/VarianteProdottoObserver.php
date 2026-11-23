<?php

namespace App\Observers;

use App\Jobs\SyncProdottoEcommerce;
use App\Models\VarianteProdotto;
use App\Services\Channels\ChannelManager;

/**
 * Se "Sincronizzazione giacenze automatica" è attiva, ogni modifica di giacenza
 * propaga lo stock ai canali e-commerce (solo mode inventory).
 */
class VarianteProdottoObserver
{
    public function saved(VarianteProdotto $variante): void
    {
        if (! $variante->wasChanged('quantita')) {
            return;
        }

        if (! app(ChannelManager::class)->syncGiacenzeAutomatica()) {
            return;
        }

        SyncProdottoEcommerce::dispatch($variante->prodotto_id, null, 'inventory');
    }
}
