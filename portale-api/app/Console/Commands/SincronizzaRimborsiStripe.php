<?php

namespace App\Console\Commands;

use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Allinea gli ordini pagati con carta ai rimborsi fatti su Stripe. Il caso normale lo copre il webhook
 * (evento charge.refunded); questo comando recupera i rimborsi passati e fa da rete di sicurezza.
 */
class SincronizzaRimborsiStripe extends Command
{
    protected $signature = 'stripe:sync-rimborsi';

    protected $description = 'Segna sugli ordini i rimborsi fatti su Stripe';

    public function handle(StripeService $stripe): int
    {
        if (! settings_integration('stripe_secret') && ! config('integrations.stripe.secret')) {
            $this->warn('Stripe non è configurato: nulla da fare.');

            return self::SUCCESS;
        }

        $aggiornati = $stripe->sincronizzaRimborsi();
        $this->info("Ordini aggiornati con un rimborso: {$aggiornati}");

        return self::SUCCESS;
    }
}
