<?php

namespace App\Console\Commands;

use App\Models\Agente;
use App\Models\Cliente;
use App\Services\Erp\ErpManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Import/aggiornamento clienti da ERP (WinMino GetClienti, incrementale via DaData).
 *
 * ATTENZIONE: i nomi dei campi WinMino non sono nella documentazione (arrivano dal
 * blocco "meta"). La mappa `map()` qui sotto usa nomi candidati e va confermata con
 * una risposta reale — vedi docs/winmino-integration.md (punto aperto #1).
 */
class SyncClienti extends Command
{
    protected $signature = 'sync:clienti {--full : ignora la data di ultimo sync e reimporta tutto}';

    protected $description = 'Import/aggiornamento clienti da ERP';

    public function handle(ErpManager $erp): int
    {
        if (! $erp->isConfigured()) {
            $this->warn('Nessun ERP configurato (Configurazioni Sistema → ERP). Skip.');

            return self::SUCCESS;
        }

        $since = $this->option('full')
            ? null
            : optional(Cliente::max('sincronizzato_erp_at'), fn ($d) => Carbon::parse($d));

        $rows = $erp->driver()->getClienti($since);
        $this->info(count($rows).' clienti ricevuti da WinMino'.($since ? " (dal {$since->format('d-m-Y')})" : ''));

        $agentiByCodice = Agente::pluck('id', 'codice_agente');
        $ok = 0;

        foreach ($rows as $row) {
            $data = $this->map($row, $agentiByCodice);
            if (blank($data['codice_cliente_erp'])) {
                continue;
            }

            Cliente::updateOrCreate(
                ['codice_cliente_erp' => $data['codice_cliente_erp']],
                $data + ['sincronizzato_erp_at' => now()],
            );
            $ok++;
        }

        $this->info("{$ok} clienti sincronizzati.");

        return self::SUCCESS;
    }

    /** @param array<string,mixed> $r */
    private function map(array $r, Collection $agentiByCodice): array
    {
        $codAgente = $this->pick($r, ['AGENTE', 'CODAGENTE', 'CODICEAGENTE']);

        return [
            'codice_cliente_erp' => (string) $this->pick($r, ['CODICE', 'CODCLIENTE', 'CODICECLIENTE', 'ID']),
            'tipo' => 'b2b',
            'ragione_sociale' => $this->pick($r, ['RAGIONESOCIALE', 'RAGSOC', 'DESCRIZIONE', 'NOME']),
            'partita_iva' => $this->pick($r, ['PARTITAIVA', 'PIVA', 'PARTIVA']),
            'codice_fiscale' => $this->pick($r, ['CODICEFISCALE', 'CODFISC', 'CF']),
            'email' => $this->pick($r, ['EMAIL', 'E_MAIL', 'MAIL']),
            'telefono' => $this->pick($r, ['TELEFONO', 'TEL']),
            'indirizzo' => $this->pick($r, ['INDIRIZZO', 'VIA']),
            'cap' => $this->pick($r, ['CAP']),
            'citta' => $this->pick($r, ['LOCALITA', 'CITTA', 'COMUNE']),
            'provincia' => $this->pick($r, ['PROVINCIA', 'PROV']),
            'agente_id' => $codAgente ? $agentiByCodice->get((string) $codAgente) : null,
            'attivo' => true,
        ];
    }

    /** @param array<string,mixed> $r @param list<string> $keys */
    private function pick(array $r, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== '') {
                return $r[$k];
            }
        }

        return null;
    }
}
