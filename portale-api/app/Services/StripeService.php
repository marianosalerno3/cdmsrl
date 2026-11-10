<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrdineB2B;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Wrapper Stripe Checkout per i pagamenti carta B2B.
 * Le credenziali arrivano da App\Settings\IntegrationSettings (pannello) con
 * fallback su config/integrations.php.
 */
class StripeService
{
    private ?StripeClient $client = null;

    /**
     * Creato solo quando serve: senza chiave Stripe configurata gli ordini con altri metodi
     * di pagamento (bonifico, contrassegno…) devono comunque funzionare.
     */
    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient($this->secret());
    }

    private function secret(): string
    {
        return (string) (settings_integration('stripe_secret') ?: config('integrations.stripe.secret'));
    }

    private function webhookSecret(): string
    {
        return (string) (settings_integration('stripe_webhook_secret') ?: config('integrations.stripe.webhook_secret'));
    }

    public function createCheckoutSession(OrdineB2B $ordine): \Stripe\Checkout\Session
    {
        $spa = rtrim((string) config('portale.spa_url'), '/');

        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => $ordine->id,
            'customer_email' => $ordine->cliente_email,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => "Ordine {$ordine->numero}"],
                    'unit_amount' => (int) round($ordine->totale * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['ordine_id' => $ordine->id, 'numero' => $ordine->numero],
            'success_url' => "{$spa}/cart?stripe=success&order={$ordine->id}&session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => "{$spa}/cart?stripe=cancel&order={$ordine->id}",
        ]);
    }

    public function confirmSession(OrdineB2B $ordine, string $sessionId): bool
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status === 'paid') {
            $ordine->update([
                'pagato_at' => now(),
                'stripe_payment_intent' => $session->payment_intent,
                'stato' => OrderStatus::ConfermatoCliente,
            ]);
            app(ConfermaOrdineCliente::class)->invia($ordine);

            return true;
        }

        return false;
    }

    public function handleWebhook(Request $request): void
    {
        $event = Webhook::constructEvent(
            $request->getContent(),
            $request->header('Stripe-Signature', ''),
            $this->webhookSecret(),
        );

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $ordine = OrdineB2B::find($session->metadata->ordine_id ?? $session->client_reference_id);
            if ($ordine) {
                $ordine->update([
                    'pagato_at' => now(),
                    'stripe_payment_intent' => $session->payment_intent,
                    'stato' => OrderStatus::ConfermatoCliente,
                ]);
                app(ConfermaOrdineCliente::class)->invia($ordine);
            }
        }

        // rimborso fatto dalla dashboard di Stripe: lo riportiamo sull'ordine
        if ($event->type === 'charge.refunded') {
            $charge = $event->data->object;
            $ordine = $charge->payment_intent
                ? OrdineB2B::where('stripe_payment_intent', $charge->payment_intent)->first()
                : null;
            if ($ordine) {
                $this->registraRimborso($ordine, ((int) $charge->amount_refunded) / 100);
            }
        }
    }

    /**
     * Segna sull'ordine quanto Stripe ha restituito. Con un rimborso totale l'ordine passa ad "Annullato",
     * a meno che sia già partito (spedito/consegnato): in quel caso lo stato lo decide chi lo gestisce.
     */
    public function registraRimborso(OrdineB2B $ordine, float $importo): void
    {
        if ($importo <= 0) {
            return;
        }

        $ordine->forceFill([
            'rimborsato_importo' => round($importo, 2),
            'rimborsato_at' => $ordine->rimborsato_at ?? now(),
        ]);

        if ($ordine->isRimborsatoTotalmente()
            && ! in_array($ordine->stato, [OrderStatus::Spedito, OrderStatus::Consegnato], true)) {
            $ordine->stato = OrderStatus::Annullato;
        }

        $ordine->save();
    }

    /**
     * Rete di sicurezza e recupero: chiede a Stripe quali ordini pagati con carta sono stati rimborsati
     * (utile per i rimborsi fatti prima che il portale li ascoltasse, o se il webhook non è arrivato).
     *
     * @return int numero di ordini aggiornati
     */
    public function sincronizzaRimborsi(): int
    {
        $aggiornati = 0;

        OrdineB2B::whereNotNull('stripe_payment_intent')
            ->whereNotNull('pagato_at')
            ->whereNull('rimborsato_at')
            ->each(function (OrdineB2B $ordine) use (&$aggiornati) {
                $intent = $this->client()->paymentIntents->retrieve($ordine->stripe_payment_intent, ['expand' => ['latest_charge']]);
                $rimborsato = ((int) ($intent->latest_charge->amount_refunded ?? 0)) / 100;

                if ($rimborsato > 0) {
                    $this->registraRimborso($ordine, $rimborsato);
                    $aggiornati++;
                }
            });

        return $aggiornati;
    }
}
