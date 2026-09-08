<?php

namespace App\Jobs;

use App\Models\OrdineB2B;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Invio di un ordine confermato all'ERP (WinMino o equivalente).
 *
 * STUB — implementazione reale nel pezzo 2c:
 *   App\Services\Erp\ErpManager->driver()->pushOrder($ordine)
 * e valorizzazione di ordini_b2b.inviato_erp_at.
 */
class InviaOrdineErp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $ordineId) {}

    public function handle(): void
    {
        $ordine = OrdineB2B::with('righe', 'cliente')->find($this->ordineId);

        if (! $ordine) {
            return;
        }

        // TODO (2c): push reale + $ordine->update(['inviato_erp_at' => now()]);
        logger()->info('InviaOrdineErp (stub)', ['ordine' => $ordine->numero]);
    }
}
