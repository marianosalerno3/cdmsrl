<?php

namespace App\Console\Commands;

use App\Models\AppConfig;
use App\Models\Cliente;
use App\Models\OrdineB2B;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cancella i clienti importati da WinMino (quelli con un codice cliente ERP), conservando quelli indicati
 * o, di default, l'unico cliente approvato e attivo con accesso al portale. I clienti nati sul portale
 * (registrazione) non hanno codice ERP e non si toccano.
 *
 * Senza --conferma mostra solo cosa succederebbe. Con --conferma cancella e spegne l'import automatico
 * dei clienti, altrimenti l'import notturno li riporterebbe tutti.
 */
class SvuotaClientiImportati extends Command
{
    protected $signature = 'clienti:svuota-importati
        {--tieni=* : ID, email o codice ERP dei clienti da conservare (se omesso: l\'unico cliente approvato e attivo con accesso al portale)}
        {--conferma : esegue davvero la cancellazione (senza, mostra solo cosa succederebbe)}';

    protected $description = 'Cancella i clienti importati da WinMino, conservando quello di test';

    public function handle(): int
    {
        $daTenere = $this->daTenere();
        if ($daTenere === null) {
            return self::FAILURE;
        }

        $importati = Cliente::withTrashed()->whereNotNull('codice_cliente_erp')->where('codice_cliente_erp', '!=', '');
        $daEliminare = (clone $importati)->whereNotIn('id', $daTenere->pluck('id'));

        $totaleImportati = (clone $importati)->count();
        $quanti = (clone $daEliminare)->count();
        $conOrdini = OrdineB2B::whereIn('cliente_id', (clone $daEliminare)->select('id'))->distinct('cliente_id')->count('cliente_id');

        $this->info("Clienti importati da WinMino: {$totaleImportati}");
        $this->info('Clienti che restano:');
        $this->table(['Ragione sociale', 'Email', 'Codice ERP', 'Stato'], $daTenere->map(fn (Cliente $c) => [
            $c->denominazione, $c->email, $c->codice_cliente_erp ?: '(nato sul portale)', $c->stato_richiesta?->value,
        ])->all());
        $this->warn("Clienti da cancellare: {$quanti}".($conOrdini ? " (di cui {$conOrdini} con ordini: gli ordini restano, ma perdono il collegamento al cliente)" : ''));

        if (! $this->option('conferma')) {
            $this->line('Nessuna modifica fatta. Per cancellare davvero rilancia con --conferma.');

            return self::SUCCESS;
        }

        $morph = (new Cliente)->getMorphClass();
        (clone $daEliminare)->pluck('id')->chunk(500)->each(function (Collection $ids) use ($morph) {
            DB::transaction(function () use ($ids, $morph) {
                DB::table('personal_access_tokens')->where('tokenable_type', $morph)->whereIn('tokenable_id', $ids)->delete();
                Cliente::withTrashed()->whereIn('id', $ids)->forceDelete();   // carrelli e destinazioni seguono a cascata
            });
        });

        // senza questo, l'import notturno li riporterebbe tutti
        AppConfig::put('sync_clienti_attivo', false);

        $this->info("{$quanti} clienti cancellati. Import automatico dei clienti spento (si riaccende da Configurazioni Sistema).");

        return self::SUCCESS;
    }

    /** @return Collection<int,Cliente>|null  null = non si può procedere (messaggio già mostrato) */
    private function daTenere(): ?Collection
    {
        $chiavi = array_values(array_filter(array_map('trim', (array) $this->option('tieni'))));

        if ($chiavi) {
            $ids = array_filter($chiavi, fn ($k) => Str::isUuid($k));
            $trovati = Cliente::where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('email', $chiavi)->orWhereIn('codice_cliente_erp', $chiavi))->get();
            if ($trovati->isEmpty()) {
                $this->error('Nessun cliente trovato con i valori indicati in --tieni: non cancello nulla.');

                return null;
            }

            return $trovati;
        }

        $candidati = Cliente::all()->filter(fn (Cliente $c) => $c->puoAccedere() && $c->stato_richiesta?->value === 'approvato')->values();
        if ($candidati->count() !== 1) {
            $this->error("Trovati {$candidati->count()} clienti approvati e attivi con accesso al portale, non uno solo: indica quello da conservare con --tieni=<email, id o codice ERP>.");
            foreach ($candidati as $c) {
                $this->line(" - {$c->denominazione} <{$c->email}> ({$c->id})");
            }

            return null;
        }

        return $candidati;
    }
}
