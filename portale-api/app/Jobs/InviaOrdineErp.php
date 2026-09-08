<?php

namespace App\Jobs;

use App\Models\OrdineB2B;
use App\Services\Erp\ErpException;
use App\Services\Erp\ErpManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Invio di un ordine confermato all'ERP (WinMino) via AddOrdineCliente.
 * Se il cliente non ha ancora il codice WinMino, prima lo crea con AddCliente.
 */
class InviaOrdineErp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $ordineId) {}

    public function handle(ErpManager $erp): void
    {
        $ordine = OrdineB2B::with('righe', 'cliente.agente')->find($this->ordineId);

        if (! $ordine || ! $erp->isConfigured()) {
            return;
        }

        $driver = $erp->driver();

        // 1. assicura il codice cliente WinMino
        if (blank($ordine->cliente?->codice_cliente_erp) && $ordine->cliente) {
            $driver->upsertCliente($ordine->cliente)->throwIfFailed();
            $ordine->cliente->refresh();
        }

        // 2. invia l'ordine
        $result = $driver->pushOrdine($ordine);

        if (! $result->success) {
            throw new ErpException("Invio ordine {$ordine->numero} a WinMino fallito: {$result->message}", $result->code ? (int) $result->code : null, $result->raw);
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('InviaOrdineErp fallito', [
            'ordine_id' => $this->ordineId,
            'error' => $e->getMessage(),
        ]);
    }
}
