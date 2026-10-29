<?php

namespace Tests\Feature;

use App\Enums\Linea;
use App\Enums\ListinoTipo;
use App\Enums\RichiestaStato;
use App\Models\Agente;
use App\Models\AppConfig;
use App\Models\Cliente;
use App\Models\Collezione;
use App\Models\OrdineB2B;
use App\Models\Prodotto;
use App\Models\Stagione;
use App\Models\VarianteProdotto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pronto o programmato si decide per collezione (stagione + linea), a mano; per le programmate
 * c'è la data di partenza. Un carrello misto diventa due ordini: il pronto con spedizione calcolata,
 * il programmato con spedizione da concordare.
 */
class ProgrammatoPerCollezioneTest extends TestCase
{
    use RefreshDatabase;

    private function variante(Stagione $s, Linea $linea, string $codice): VarianteProdotto
    {
        $p = Prodotto::create(['codice' => $codice, 'nome' => 'Abito '.$codice, 'prezzo_base' => 100, 'attivo' => true, 'stagione_id' => $s->id, 'linea' => $linea]);

        return VarianteProdotto::create(['prodotto_id' => $p->id, 'sku' => 'SKU'.$codice, 'prezzo' => 100, 'quantita' => 10]);
    }

    public function test_programmato_si_decide_per_stagione_e_linea(): void
    {
        $pe27 = Stagione::create(['codice' => 'PE27', 'attiva' => true]);
        Collezione::create(['stagione_id' => $pe27->id, 'linea' => 'CLASSE DI VALENTINA', 'programmata' => true]);
        Collezione::create(['stagione_id' => $pe27->id, 'linea' => 'CLARA G', 'programmata' => false]);

        $this->assertTrue($this->variante($pe27, Linea::ClasseDiValentina, 'A1')->prodotto->isProgrammato());
        $this->assertFalse($this->variante($pe27, Linea::ClaraG, 'A2')->prodotto->isProgrammato());
        // linea mai censita nella stagione: pronto consegna
        $this->assertFalse($this->variante($pe27, Linea::Oltretempo, 'A3')->prodotto->isProgrammato());
    }

    public function test_il_dettaglio_prodotto_espone_programmato_e_data_di_partenza(): void
    {
        $agente = Agente::create([
            'codice_agente' => 'A300', 'nome' => 'Agente', 'email' => 'ag@example.test',
            'password' => 'password-agente', 'listino_default' => ListinoTipo::Standard, 'attivo' => true,
        ]);
        $pe27 = Stagione::create(['codice' => 'PE27', 'attiva' => true]);
        Collezione::create(['stagione_id' => $pe27->id, 'linea' => 'CLASSE DI VALENTINA', 'programmata' => true, 'data_partenza' => '2027-02-15']);
        $programmato = $this->variante($pe27, Linea::ClasseDiValentina, 'B1')->prodotto;
        $pronto = $this->variante($pe27, Linea::ClaraG, 'B2')->prodotto;

        $this->actingAs($agente, 'sanctum')->getJson("/api/products/{$programmato->id}")
            ->assertOk()->assertJsonPath('data.programmata', true)->assertJsonPath('data.data_partenza', '2027-02-15');
        $this->actingAs($agente, 'sanctum')->getJson("/api/products/{$pronto->id}")
            ->assertOk()->assertJsonPath('data.programmata', false)->assertJsonPath('data.data_partenza', null);
    }

    public function test_un_carrello_misto_diventa_due_ordini(): void
    {
        Mail::fake();
        AppConfig::put('spedizione_pronto_fissa', 10);
        AppConfig::put('spedizione_pronto_soglia', 300);
        $pe27 = Stagione::create(['codice' => 'PE27', 'attiva' => true]);
        Collezione::create(['stagione_id' => $pe27->id, 'linea' => 'CLASSE DI VALENTINA', 'programmata' => true]);
        $programmato = $this->variante($pe27, Linea::ClasseDiValentina, 'C1');
        $pronto = $this->variante($pe27, Linea::ClaraG, 'C2');
        $cliente = Cliente::create([
            'ragione_sociale' => 'Boutique Rossi Srl', 'partita_iva' => 'IT12345678', 'email' => 'rossi@example.test',
            'password' => 'password-cliente', 'tipo' => 'b2b', 'stato_richiesta' => RichiestaStato::Approvato, 'attivo' => true,
            'tipo_listino' => ListinoTipo::Standard, 'indirizzo' => 'Via Roma 1', 'cap' => '80100', 'citta' => 'Napoli', 'provincia' => 'NA',
        ]);

        $res = $this->actingAs($cliente, 'sanctum')->postJson('/api/orders', [
            'metodo_pagamento' => 'stripe',
            'righe' => [
                ['variante_id' => $programmato->id, 'quantita' => 2],
                ['variante_id' => $pronto->id, 'quantita' => 1],
            ],
        ])->assertCreated();

        $this->assertSame(2, OrdineB2B::count());
        $res->assertJsonCount(2, 'orders')
            ->assertJsonPath('data.programmato', false)        // `data` è il pronto, quello da pagare
            ->assertJsonPath('data.metodo_pagamento', 'stripe')
            ->assertJsonPath('data.spese_spedizione', 10);     // calcolata in automatico
        $ordineProgrammato = OrdineB2B::where('programmato', true)->firstOrFail();
        $this->assertSame(0.0, (float) $ordineProgrammato->spese_spedizione); // da concordare col commerciale
        $this->assertSame('bonifico', $ordineProgrammato->metodo_pagamento->value); // il programmato non si paga con carta a carrello
        $this->assertSame(1, $ordineProgrammato->righe()->count());
        $this->assertSame(8, $programmato->fresh()->quantita);
        $this->assertSame(9, $pronto->fresh()->quantita);
    }

    public function test_un_carrello_solo_pronto_resta_un_ordine(): void
    {
        Mail::fake();
        $pe27 = Stagione::create(['codice' => 'PE27', 'attiva' => true]);
        $pronto = $this->variante($pe27, Linea::ClaraG, 'D1');
        $cliente = Cliente::create([
            'ragione_sociale' => 'Boutique Verdi Srl', 'partita_iva' => 'IT87654321', 'email' => 'verdi@example.test',
            'password' => 'password-cliente', 'tipo' => 'b2b', 'stato_richiesta' => RichiestaStato::Approvato, 'attivo' => true,
            'tipo_listino' => ListinoTipo::Standard, 'indirizzo' => 'Via Roma 2', 'cap' => '80100', 'citta' => 'Napoli', 'provincia' => 'NA',
        ]);

        $this->actingAs($cliente, 'sanctum')->postJson('/api/orders', [
            'metodo_pagamento' => 'bonifico', 'righe' => [['variante_id' => $pronto->id, 'quantita' => 1]],
        ])->assertCreated()->assertJsonCount(1, 'orders');

        $this->assertSame(1, OrdineB2B::count());
    }
}
