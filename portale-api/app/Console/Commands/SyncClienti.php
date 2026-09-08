<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Import anagrafica clienti dall'ERP (WinMino o equivalente).
 * STUB — implementazione reale nel pezzo 2c.
 */
class SyncClienti extends Command
{
    protected $signature = 'sync:clienti';

    protected $description = 'Import/aggiornamento clienti da ERP';

    public function handle(): int
    {
        $this->info('sync:clienti (stub)');

        // TODO (2c): ErpManager->driver()->fetchCustomers() -> upsert su clienti.
        return self::SUCCESS;
    }
}
