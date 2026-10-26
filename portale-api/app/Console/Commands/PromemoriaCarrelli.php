<?php

namespace App\Console\Commands;

use App\Mail\CarrelloFermoAgenteMail;
use App\Mail\CarrelloFermoMail;
use App\Models\AppConfig;
use App\Models\Carrello;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Promemoria per i carrelli fermi (una volta sola per carrello, riparte se il carrello cambia).
 *
 *  - cliente con accesso al portale → email al cliente, ma solo se ha dato il consenso alle comunicazioni commerciali
 *    (i clienti vecchi senza consenso non ricevono nulla)
 *  - cliente senza accesso al portale, carrello caricato da un agente → email all'agente
 */
class PromemoriaCarrelli extends Command
{
    protected $signature = 'carrelli:promemoria {--dry-run : mostra a chi scriverebbe, senza inviare}';

    protected $description = 'Invia il promemoria per i carrelli fermi';

    /** oltre questa età il carrello non è più "appena lasciato": niente promemoria */
    private const GIORNI_MASSIMI = 7;

    public function handle(): int
    {
        if (! (bool) AppConfig::get('promemoria_carrello_attivo', true)) {
            $this->info('Promemoria carrello disattivato (Configurazioni Sistema).');

            return self::SUCCESS;
        }

        $ore = max(1, (int) AppConfig::get('promemoria_carrello_ore', 24));
        $dry = (bool) $this->option('dry-run');
        $alCliente = 0;
        $alAgente = 0;

        Carrello::query()
            ->whereNull('promemoria_inviato_at')
            ->where('aggiornato_at', '<=', now()->subHours($ore))
            ->where('aggiornato_at', '>=', now()->subDays(self::GIORNI_MASSIMI))
            ->with(['cliente', 'agente'])
            ->each(function (Carrello $c) use ($dry, &$alCliente, &$alAgente) {
                $cliente = $c->cliente;
                if (! $cliente || $c->righe === []) {
                    return;
                }

                if ($cliente->puoAccedere()) {
                    // sul portale: scriviamo al cliente, ma solo con il suo consenso
                    if (! $cliente->haConsensoComunicazioni() || blank($cliente->email)) {
                        return;
                    }
                    $this->line("→ cliente {$cliente->denominazione} <{$cliente->email}>");
                    if (! $dry) {
                        Mail::to($cliente->email)->queue(new CarrelloFermoMail($c));
                        $c->forceFill(['promemoria_inviato_at' => now()])->saveQuietly();
                    }
                    $alCliente++;

                    return;
                }

                // cliente non presente sul portale: se l'ha caricato un agente, avvisiamo lui
                $agente = $c->agente;
                if ($agente && $agente->attivo && filled($agente->email)) {
                    $this->line("→ agente {$agente->nome} <{$agente->email}> per {$cliente->denominazione}");
                    if (! $dry) {
                        Mail::to($agente->email)->queue(new CarrelloFermoAgenteMail($c));
                        $c->forceFill(['promemoria_inviato_at' => now()])->saveQuietly();
                    }
                    $alAgente++;
                }
            });

        $this->info(($dry ? '[prova] ' : '')."Promemoria: {$alCliente} ai clienti, {$alAgente} agli agenti.");

        return self::SUCCESS;
    }
}
