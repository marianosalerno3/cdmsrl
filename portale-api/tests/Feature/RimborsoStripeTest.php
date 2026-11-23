<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\OrdineB2B;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un rimborso fatto su Stripe si riflette sull'ordine: importo, data e, se totale, stato annullato. */
class RimborsoStripeTest extends TestCase
{
    use RefreshDatabase;

    private function ordinePagato(array $over = []): OrdineB2B
    {
        return OrdineB2B::create($over + [
            'cliente_nome' => 'Boutique Rossi', 'cliente_email' => 'rossi@example.test', 'indirizzo_spedizione' => 'Via Roma 1, 80100 Napoli (NA)',
            'stato' => OrderStatus::ConfermatoCliente, 'metodo_pagamento' => 'stripe', 'listino_applicato' => 'standard',
            'subtotale' => 10, 'spese_spedizione' => 0, 'iva_perc' => 22, 'iva_importo' => 2.2, 'totale' => 12.2, 'totale_pezzi' => 1,
            'pagato_at' => now(), 'stripe_payment_intent' => 'pi_test_123',
        ]);
    }

    public function test_rimborso_totale_annulla_l_ordine(): void
    {
        $ordine = $this->ordinePagato();

        app(StripeService::class)->registraRimborso($ordine, 12.20);

        $ordine->refresh();
        $this->assertTrue($ordine->isRimborsatoTotalmente());
        $this->assertSame(OrderStatus::Annullato, $ordine->stato);
        $this->assertEquals(12.20, (float) $ordine->rimborsato_importo);
        $this->assertNotNull($ordine->rimborsato_at);
    }

    public function test_rimborso_parziale_non_cambia_lo_stato(): void
    {
        $ordine = $this->ordinePagato();

        app(StripeService::class)->registraRimborso($ordine, 5.00);

        $ordine->refresh();
        $this->assertTrue($ordine->isRimborsato());
        $this->assertFalse($ordine->isRimborsatoTotalmente());
        $this->assertSame(OrderStatus::ConfermatoCliente, $ordine->stato);
    }

    public function test_un_ordine_gia_spedito_non_viene_annullato(): void
    {
        $ordine = $this->ordinePagato(['stato' => OrderStatus::Spedito]);

        app(StripeService::class)->registraRimborso($ordine, 12.20);

        $this->assertSame(OrderStatus::Spedito, $ordine->fresh()->stato);
        $this->assertTrue($ordine->fresh()->isRimborsatoTotalmente());
    }

    public function test_il_webhook_charge_refunded_aggiorna_l_ordine(): void
    {
        config(['integrations.stripe.webhook_secret' => 'whsec_test']);
        $ordine = $this->ordinePagato();

        $payload = json_encode([
            'id' => 'evt_1', 'object' => 'event', 'type' => 'charge.refunded',
            'data' => ['object' => ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_test_123', 'amount' => 1220, 'amount_refunded' => 1220]],
        ]);
        $t = time();
        $firma = "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');

        $this->call('POST', '/api/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $firma, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Annullato, $ordine->fresh()->stato);
        $this->assertEquals(12.20, (float) $ordine->fresh()->rimborsato_importo);
    }
}
