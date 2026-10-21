<?php

namespace App\Services;

use App\Mail\OrdineConfermaMail;
use App\Models\OrdineB2B;
use Illuminate\Support\Facades\Mail;

/**
 * Email di conferma ordine al cliente, in automatico.
 *  - bonifico, contrassegno…: alla creazione dell'ordine
 *  - carta (Stripe): quando il pagamento risulta incassato
 *
 * Si invia una sola volta: il ritorno dal pagamento e il webhook di Stripe arrivano quasi insieme,
 * e solo chi riesce a valorizzare `email_conferma_inviata_at` manda la mail.
 */
class ConfermaOrdineCliente
{
    public function invia(OrdineB2B $ordine): bool
    {
        if (blank($ordine->cliente_email)) {
            return false;
        }

        $presaInCarico = OrdineB2B::whereKey($ordine->getKey())
            ->whereNull('email_conferma_inviata_at')
            ->update(['email_conferma_inviata_at' => now()]) === 1;

        if (! $presaInCarico) {
            return false;
        }

        Mail::to($ordine->cliente_email)->queue(new OrdineConfermaMail($ordine));

        return true;
    }
}
