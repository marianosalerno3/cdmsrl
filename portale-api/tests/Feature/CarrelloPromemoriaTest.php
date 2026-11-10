<?php

namespace Tests\Feature;

use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Mail\CarrelloFermoAgenteMail;
use App\Mail\CarrelloFermoMail;
use App\Models\Agente;
use App\Models\AppConfig;
use App\Models\Carrello;
use App\Models\Cliente;
use App\Models\Prodotto;
use App\Models\VarianteProdotto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Carrello salvato sul server e promemoria per i carrelli fermi:
 *  - al cliente (se ha l'accesso e ha dato il consenso), mai ai clienti senza consenso
 *  - all'agente se il cliente non è sul portale e il carrello l'ha caricato lui
 */
class CarrelloPromemoriaTest extends TestCase
{
    use RefreshDatabase;

    private function agente(): Agente
    {
        return Agente::create([
            'codice_agente' => 'A'.random_int(100, 999), 'nome' => 'Agente Test', 'email' => 'agente@example.test',
            'password' => 'password-agente', 'listino_default' => ListinoTipo::Standard, 'attivo' => true,
        ]);
    }

    /** Cliente con accesso al portale (password, approvato, attivo). */
    private function cliente(array $over = []): Cliente
    {
        return Cliente::create($over + [
            'ragione_sociale' => 'Boutique Rossi Srl', 'partita_iva' => 'IT'.random_int(10000000, 99999999),
            'email' => 'rossi@example.test', 'password' => 'password-cliente', 'tipo' => 'b2b',
            'stato_richiesta' => RichiestaStato::Approvato, 'attivo' => true, 'tipo_listino' => ListinoTipo::Plus10,
            'indirizzo' => 'Via Roma 1', 'cap' => '80100', 'citta' => 'Napoli', 'provincia' => 'NA',
        ]);
    }

    private function variante(): VarianteProdotto
    {
        $p = Prodotto::create(['codice' => 'T'.random_int(1000, 9999), 'nome' => 'Giacca test', 'prezzo_base' => 100, 'attivo' => true]);

        return VarianteProdotto::create(['prodotto_id' => $p->id, 'sku' => 'SKU'.random_int(10000, 99999), 'prezzo' => 100, 'quantita' => 10]);
    }

    private function carrelloFermo(Cliente $cliente, ?Agente $agente = null, int $oreFa = 30): Carrello
    {
        return Carrello::create([
            'cliente_id' => $cliente->id,
            'agente_id' => $agente?->id,
            'righe' => [['varianteId' => 'v1', 'nome' => 'Giacca', 'taglia' => '46', 'colore' => 'NERO', 'prezzo' => 50.0, 'quantita' => 2]],
            'totale' => 100,
            'aggiornato_at' => now()->subHours($oreFa),
        ]);
    }

    public function test_il_cliente_salva_e_riprende_il_proprio_carrello(): void
    {
        $cliente = $this->cliente();
        $item = ['varianteId' => 'v1', 'nome' => 'Giacca', 'prezzo' => 50, 'quantita' => 3];

        $this->actingAs($cliente, 'sanctum')->putJson('/api/cart', ['items' => [$item]])->assertOk();
        $this->assertSame(150.0, (float) Carrello::first()->totale);

        $this->actingAs($cliente, 'sanctum')->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.quantita', 3);

        // carrello svuotato: sparisce dal server
        $this->actingAs($cliente, 'sanctum')->putJson('/api/cart', ['items' => []])->assertOk();
        $this->assertSame(0, Carrello::count());
    }

    public function test_l_agente_lavora_solo_sui_carrelli_dei_propri_clienti(): void
    {
        $agente = $this->agente();
        $suo = $this->cliente(['agente_id' => $agente->id, 'email' => 'suo@example.test']);
        $altrui = $this->cliente(['email' => 'altrui@example.test']);
        $item = ['varianteId' => 'v1', 'nome' => 'Giacca', 'prezzo' => 50, 'quantita' => 1];

        $this->actingAs($agente, 'sanctum')->putJson('/api/cart', ['cliente_id' => $suo->id, 'items' => [$item]])->assertOk();
        $this->assertSame($agente->id, Carrello::first()->agente_id);

        $this->actingAs($agente, 'sanctum')->putJson('/api/cart', ['cliente_id' => $altrui->id, 'items' => [$item]])->assertForbidden();
    }

    public function test_l_ordine_elimina_il_carrello_salvato(): void
    {
        Mail::fake();
        $cliente = $this->cliente();
        $v = $this->variante();
        $this->carrelloFermo($cliente);

        $this->actingAs($cliente, 'sanctum')->postJson('/api/orders', [
            'metodo_pagamento' => 'bonifico',
            'righe' => [['variante_id' => $v->id, 'quantita' => 1]],
        ])->assertCreated();

        $this->assertSame(0, Carrello::count());
    }

    public function test_promemoria_al_cliente_solo_con_consenso(): void
    {
        Mail::fake();
        $conConsenso = $this->cliente(['email' => 'si@example.test', 'consenso_comunicazioni_at' => now()->subDays(10)]);
        $senzaConsenso = $this->cliente(['email' => 'no@example.test']);
        $revocato = $this->cliente(['email' => 'revocato@example.test', 'consenso_comunicazioni_at' => now()->subDays(10), 'consenso_comunicazioni_revocato_at' => now()->subDay()]);
        foreach ([$conConsenso, $senzaConsenso, $revocato] as $c) {
            $this->carrelloFermo($c);
        }

        $this->artisan('carrelli:promemoria')->assertSuccessful();

        Mail::assertQueued(CarrelloFermoMail::class, 1);
        Mail::assertQueued(CarrelloFermoMail::class, fn ($m) => $m->hasTo('si@example.test'));
        Mail::assertNotQueued(CarrelloFermoAgenteMail::class);
        $this->assertNotNull(Carrello::where('cliente_id', $conConsenso->id)->first()->promemoria_inviato_at);

        // un secondo giro non rimanda lo stesso promemoria
        $this->artisan('carrelli:promemoria')->assertSuccessful();
        Mail::assertQueued(CarrelloFermoMail::class, 1);
    }

    public function test_cliente_non_sul_portale_avvisa_l_agente_che_ha_caricato_il_carrello(): void
    {
        Mail::fake();
        $agente = $this->agente();
        $senzaAccesso = $this->cliente(['agente_id' => $agente->id, 'password' => null, 'email' => null, 'ragione_sociale' => 'Cliente ERP Srl']);
        $this->carrelloFermo($senzaAccesso, $agente);

        $this->artisan('carrelli:promemoria')->assertSuccessful();

        Mail::assertQueued(CarrelloFermoAgenteMail::class, fn ($m) => $m->hasTo('agente@example.test'));
        Mail::assertNotQueued(CarrelloFermoMail::class);
    }

    public function test_niente_promemoria_se_troppo_presto_troppo_vecchio_o_disattivato(): void
    {
        Mail::fake();
        $c = fn (string $mail) => $this->cliente(['email' => $mail, 'consenso_comunicazioni_at' => now()->subDays(30)]);
        $this->carrelloFermo($c('presto@example.test'), null, 3);    // fermo da sole 3 ore
        $this->carrelloFermo($c('vecchio@example.test'), null, 24 * 20); // fermo da 20 giorni

        $this->artisan('carrelli:promemoria')->assertSuccessful();
        Mail::assertNotQueued(CarrelloFermoMail::class);
        Mail::assertNotQueued(CarrelloFermoAgenteMail::class);

        $this->carrelloFermo($c('ok@example.test'));
        AppConfig::put('promemoria_carrello_attivo', false);
        $this->artisan('carrelli:promemoria')->assertSuccessful();
        Mail::assertNotQueued(CarrelloFermoMail::class);
        Mail::assertNotQueued(CarrelloFermoAgenteMail::class);
    }

    public function test_la_modifica_del_carrello_riavvia_il_promemoria(): void
    {
        Mail::fake();
        $cliente = $this->cliente(['consenso_comunicazioni_at' => now()->subDays(10)]);
        $carrello = $this->carrelloFermo($cliente);
        $carrello->update(['promemoria_inviato_at' => now()->subHour()]);

        $this->actingAs($cliente, 'sanctum')->putJson('/api/cart', [
            'items' => [['varianteId' => 'v2', 'nome' => 'Gonna', 'prezzo' => 40, 'quantita' => 1]],
        ])->assertOk();

        $this->assertNull($carrello->fresh()->promemoria_inviato_at);
    }

    public function test_il_link_annulla_iscrizione_revoca_il_consenso(): void
    {
        $cliente = $this->cliente(['consenso_comunicazioni_at' => now()->subDays(2)]);
        $this->assertTrue($cliente->haConsensoComunicazioni());

        // senza firma il link non funziona
        $this->get(route('comunicazioni.annulla', $cliente))->assertForbidden();

        $this->get(URL::signedRoute('comunicazioni.annulla', ['cliente' => $cliente->id]))->assertOk();

        $this->assertFalse($cliente->fresh()->haConsensoComunicazioni());
    }

    public function test_la_registrazione_richiede_il_consenso_alle_comunicazioni(): void
    {
        Mail::fake();
        $dati = [
            'ragione_sociale' => 'Nuova Moda Srl', 'partita_iva' => '12345678901', 'email' => 'nuova@example.test',
            'password' => 'una-password-lunga', 'password_confirmation' => 'una-password-lunga', 'pec' => 'nuova@pec.example.test',
            'accetto_privacy' => true, 'indirizzo' => 'Via Milano 5', 'cap' => '20100', 'citta' => 'Milano', 'provincia' => 'MI',
        ];

        $this->postJson('/api/register-b2b', $dati)->assertStatus(422)->assertJsonValidationErrors('accetto_comunicazioni');

        $this->postJson('/api/register-b2b', $dati + ['accetto_comunicazioni' => true])->assertCreated();
        $this->assertTrue(Cliente::where('email', 'nuova@example.test')->first()->haConsensoComunicazioni());
    }
}
