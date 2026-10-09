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
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient($this->secret());
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

        return $this->client->checkout->sessions->create([
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
        $session = $this->client->checkout->sessions->retrieve($sessionId);

        if ($session->payment_status === 'paid') {
            $ordine->update([
                'pagato_at' => now(),
                'stripe_payment_intent' => $session->payment_intent,
                'stato' => OrderStatus::ConfermatoCliente,
            ]);

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
            $ordine?->update([
                'pagato_at' => now(),
                'stripe_payment_intent' => $session->payment_intent,
                'stato' => OrderStatus::ConfermatoCliente,
            ]);
        }
    }
}
