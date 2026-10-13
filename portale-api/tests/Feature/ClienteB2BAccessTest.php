<?php

namespace Tests\Feature;

use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Mail\ClienteApprovatoMail;
use App\Mail\NuovaRegistrazioneB2BMail;
use App\Models\Agente;
use App\Models\Cliente;
use App\Models\OrdineB2B;
use App\Models\Prodotto;
use App\Models\VarianteProdotto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Accesso dei clienti B2B: registrazione → approvazione admin → login → catalogo col proprio listino → ordine.
 */
class ClienteB2BAccessTest extends TestCase
{
    use RefreshDatabase;

    private function agente(string $email = 'agente@example.test'): Agente
    {
        return Agente::create([
            'codice_agente' => 'A'.random_int(100, 999),
            'nome' => 'Agente Test',
            'email' => $email,
            'password' => 'password-agente',
            'listino_default' => ListinoTipo::Standard,
            'attivo' => true,
        ]);
    }

    private function cliente(array $over = []): Cliente
    {
        return Cliente::create($over + [
            'ragione_sociale' => 'Boutique Rossi Srl',
            'partita_iva' => 'IT'.random_int(10000000, 99999999),
            'email' => 'rossi@example.test',
            'password' => 'password-cliente',
            'tipo' => 'b2b',
            'stato_richiesta' => RichiestaStato::Approvato,
            'attivo' => true,
            'tipo_listino' => ListinoTipo::Plus10,
            'indirizzo' => 'Via Roma 1', 'cap' => '80100', 'citta' => 'Napoli', 'provincia' => 'NA',
        ]);
    }

    private function variante(float $prezzo = 100.0, int $qta = 10): VarianteProdotto
    {
        $p = Prodotto::create(['codice' => 'T'.random_int(1000, 9999), 'nome' => 'Giacca test', 'prezzo_base' => $prezzo, 'attivo' => true]);

        return VarianteProdotto::create(['prodotto_id' => $p->id, 'sku' => 'SKU'.random_int(10000, 99999), 'prezzo' => $prezzo, 'quantita' => $qta]);
    }

    private function registrazione(array $over = []): array
    {
        return $over + [
            'ragione_sociale' => 'Nuova Moda Srl',
            'partita_iva' => '12345678901',
            'email' => 'nuova@example.test',
            'password' => 'una-password-lunga',
            'password_confirmation' => 'una-password-lunga',
            'pec' => 'nuova@pec.example.test',
            'indirizzo' => 'Via Milano 5', 'cap' => '20100', 'citta' => 'Milano', 'provincia' => 'MI',
        ];
    }

    public function test_registrazione_crea_cliente_in_attesa_e_avvisa_il_backoffice(): void
    {
        Mail::fake();
        config(['portale.backoffice_email' => 'backoffice@example.test']);

        $this->postJson('/api/register-b2b', $this->registrazione())->assertCreated();

        $c = Cliente::where('email', 'nuova@example.test')->firstOrFail();
        $this->assertSame(RichiestaStato::InAttesa, $c->stato_richiesta);
        $this->assertFalse($c->attivo);
        $this->assertNotSame('una-password-lunga', $c->password); // salvata hashata
        Mail::assertQueued(NuovaRegistrazioneB2BMail::class);
    }

    public function test_approvazione_da_qualunque_punto_invia_una_sola_email_al_cliente(): void
    {
        Mail::fake();

        $this->postJson('/api/register-b2b', $this->registrazione())->assertCreated();
        $cliente = Cliente::where('email', 'nuova@example.test')->firstOrFail();
        Mail::assertNotQueued(ClienteApprovatoMail::class); // ancora in attesa

        // approvazione dalla scheda di modifica (non dal pulsante Approva)
        $cliente->update(['stato_richiesta' => RichiestaStato::Approvato, 'attivo' => true]);

        Mail::assertQueued(ClienteApprovatoMail::class, 1);
        $this->assertNotNull($cliente->fresh()->approvato_at);

        // ulteriori modifiche non rimandano l'email
        $cliente->fresh()->update(['telefono' => '0811234567']);
        Mail::assertQueued(ClienteApprovatoMail::class, 1);
    }

    public function test_cliente_approvato_senza_password_non_riceve_email_finche_non_ha_accesso(): void
    {
        Mail::fake();
        $cliente = $this->cliente(['password' => null, 'email' => 'erp@example.test']);
        Mail::assertNotQueued(ClienteApprovatoMail::class);

        $cliente->update(['password' => 'nuova-password-1']);
        Mail::assertQueued(ClienteApprovatoMail::class, 1);
    }

    public function test_registrazione_richiede_pec_o_codice_sdi(): void
    {
        Mail::fake();
        $senza = $this->registrazione();
        unset($senza['pec']);

        $this->postJson('/api/register-b2b', $senza)
            ->assertStatus(422)->assertJsonValidationErrors(['pec', 'codice_sdi']);

        // solo il codice SDI basta (e viene salvato maiuscolo)
        $this->postJson('/api/register-b2b', $senza + ['codice_sdi' => 'abc1234'])->assertCreated();
        $this->assertSame('ABC1234', Cliente::where('email', 'nuova@example.test')->value('codice_sdi'));

        // un codice SDI malformato è rifiutato
        $this->postJson('/api/register-b2b', $this->registrazione(['pec' => null, 'codice_sdi' => 'XY', 'email' => 'x@example.test', 'partita_iva' => '99988877766']))
            ->assertStatus(422)->assertJsonValidationErrors('codice_sdi');
    }

    public function test_registrazione_rifiuta_partita_iva_gia_presente_ed_email_gia_usata(): void
    {
        $this->cliente(['partita_iva' => '12345678901', 'email' => 'altro@example.test']);

        $this->postJson('/api/register-b2b', $this->registrazione())
            ->assertStatus(422)->assertJsonValidationErrors('partita_iva');

        $this->cliente(['partita_iva' => '99999999999', 'email' => 'nuova@example.test']);
        $this->postJson('/api/register-b2b', $this->registrazione(['partita_iva' => '55555555555']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_cliente_in_attesa_non_entra_finche_non_approvato(): void
    {
        $this->postJson('/api/register-b2b', $this->registrazione())->assertCreated();

        $this->postJson('/api/login', ['email' => 'nuova@example.test', 'password' => 'una-password-lunga'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        Cliente::where('email', 'nuova@example.test')->update(['stato_richiesta' => 'approvato', 'attivo' => true]);

        $this->postJson('/api/login', ['email' => 'nuova@example.test', 'password' => 'una-password-lunga'])
            ->assertOk()
            ->assertJsonPath('user.ruolo', 'cliente')
            ->assertJsonStructure(['token']);
    }

    public function test_password_sbagliata_non_entra(): void
    {
        $this->cliente();

        $this->postJson('/api/login', ['email' => 'rossi@example.test', 'password' => 'sbagliata'])
            ->assertStatus(422);
    }

    public function test_catalogo_del_cliente_usa_il_suo_listino(): void
    {
        $v = $this->variante(100.0);
        $cliente = $this->cliente(['tipo_listino' => ListinoTipo::Plus10]);

        $atteso = round(100 * ListinoTipo::Plus10->multiplier(), 2);

        $res = $this->actingAs($cliente, 'sanctum')->getJson('/api/products')->assertOk();
        $this->assertSame('plus10', $res->json('listino'));
        $this->assertEqualsWithDelta($atteso, (float) $res->json('data.0.prezzo_min'), 0.01);
    }

    public function test_cliente_ordina_da_solo_ed_e_attribuito_al_suo_agente(): void
    {
        Mail::fake();
        $agente = $this->agente();
        $cliente = $this->cliente(['agente_id' => $agente->id, 'tipo_listino' => ListinoTipo::Plus10]);
        $v = $this->variante(100.0, 10);

        $res = $this->actingAs($cliente, 'sanctum')->postJson('/api/orders', [
            'metodo_pagamento' => 'bonifico',
            'righe' => [['variante_id' => $v->id, 'quantita' => 2]],
        ])->assertCreated();

        $ordine = OrdineB2B::findOrFail($res->json('data.id'));
        $this->assertTrue($ordine->da_cliente);
        $this->assertSame($agente->id, $ordine->agente_id);
        $this->assertSame($cliente->id, $ordine->cliente_id);
        $this->assertEqualsWithDelta(round(100 * ListinoTipo::Plus10->multiplier(), 2) * 2, (float) $ordine->subtotale, 0.01);
        $this->assertSame(8, $v->fresh()->quantita);
    }

    public function test_cliente_senza_agente_puo_ordinare_e_contrassegno_va_abilitato(): void
    {
        Mail::fake();
        $cliente = $this->cliente(['agente_id' => null, 'contrassegno_abilitato' => false]);
        $v = $this->variante();

        $righe = [['variante_id' => $v->id, 'quantita' => 1]];

        $this->actingAs($cliente, 'sanctum')->postJson('/api/orders', ['metodo_pagamento' => 'contrassegno', 'righe' => $righe])
            ->assertStatus(422);

        $this->actingAs($cliente, 'sanctum')->postJson('/api/orders', ['metodo_pagamento' => 'bonifico', 'righe' => $righe])
            ->assertCreated();
    }

    public function test_cliente_ignora_il_cliente_id_inviato_e_non_vede_ordini_altrui(): void
    {
        Mail::fake();
        $mio = $this->cliente(['email' => 'mio@example.test']);
        $altro = $this->cliente(['email' => 'altro@example.test']);
        $v = $this->variante();

        $res = $this->actingAs($mio, 'sanctum')->postJson('/api/orders', [
            'cliente_id' => $altro->id, // tentativo di ordinare per un altro
            'metodo_pagamento' => 'bonifico',
            'righe' => [['variante_id' => $v->id, 'quantita' => 1]],
        ])->assertCreated();
        $this->assertSame($mio->id, OrdineB2B::findOrFail($res->json('data.id'))->cliente_id);

        $ordineAltro = OrdineB2B::create([
            'cliente_id' => $altro->id, 'cliente_nome' => 'Altro', 'indirizzo_spedizione' => 'x',
        ]);

        $this->actingAs($mio, 'sanctum')->getJson("/api/orders/{$ordineAltro->id}")->assertForbidden();
        $this->actingAs($mio, 'sanctum')->deleteJson("/api/orders/{$ordineAltro->id}")->assertForbidden();
    }

    public function test_cliente_vede_solo_i_propri_ordini_e_se_stesso_come_cliente(): void
    {
        Mail::fake();
        $mio = $this->cliente(['email' => 'mio@example.test']);
        $altro = $this->cliente(['email' => 'altro@example.test']);
        OrdineB2B::create(['cliente_id' => $mio->id, 'cliente_nome' => 'Mio', 'indirizzo_spedizione' => 'x']);
        OrdineB2B::create(['cliente_id' => $altro->id, 'cliente_nome' => 'Altro', 'indirizzo_spedizione' => 'x']);

        $this->actingAs($mio, 'sanctum')->getJson('/api/agent/orders')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($mio, 'sanctum')->getJson('/api/agent/customers')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mio->id);
    }

    public function test_cliente_non_accede_alle_rotte_riservate_agli_agenti(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($cliente, 'sanctum')->postJson('/api/clients', [])->assertForbidden();
        $this->actingAs($cliente, 'sanctum')->getJson('/api/agent/returns')->assertForbidden();
    }

    public function test_cliente_disattivato_perde_l_accesso_anche_con_token_valido(): void
    {
        $cliente = $this->cliente();

        $token = $this->postJson('/api/login', ['email' => 'rossi@example.test', 'password' => 'password-cliente'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $cliente->update(['attivo' => false]);
        $this->app['auth']->forgetGuards(); // i test riusano la stessa istanza della guard: simula una nuova richiesta

        $this->withToken($token)->getJson('/api/me')->assertForbidden();
    }

    public function test_recupero_password_del_cliente_rimanda_al_backoffice(): void
    {
        $this->cliente(['email' => 'rossi@example.test']);

        $res = $this->postJson('/api/forgot-password', ['email' => 'rossi@example.test'])->assertOk();
        $this->assertStringContainsString('backoffice', $res->json('message'));
    }

    public function test_flusso_agente_resta_invariato(): void
    {
        Mail::fake();
        $agente = $this->agente();
        $suo = $this->cliente(['agente_id' => $agente->id, 'email' => 'suo@example.test']);
        $altrui = $this->cliente(['agente_id' => $this->agente('b@example.test')->id, 'email' => 'altrui@example.test']);
        $v = $this->variante();

        $token = $this->postJson('/api/login', ['email' => 'agente@example.test', 'password' => 'password-agente'])
            ->assertOk()->assertJsonPath('user.ruolo', 'agente')->json('token');

        $righe = [['variante_id' => $v->id, 'quantita' => 1]];

        $res = $this->withToken($token)->postJson('/api/orders', ['cliente_id' => $suo->id, 'metodo_pagamento' => 'bonifico', 'righe' => $righe])
            ->assertCreated();
        $this->assertFalse(OrdineB2B::findOrFail($res->json('data.id'))->da_cliente);

        $this->withToken($token)->postJson('/api/orders', ['cliente_id' => $altrui->id, 'metodo_pagamento' => 'bonifico', 'righe' => $righe])
            ->assertForbidden();

        $this->withToken($token)->getJson('/api/agent/customers')->assertOk()->assertJsonCount(1, 'data');
    }
}
