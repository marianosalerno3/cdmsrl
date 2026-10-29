<?php

namespace Tests\Feature;

use App\Enums\Linea;
use App\Enums\ListinoTipo;
use App\Models\Agente;
use App\Models\Prodotto;
use App\Models\Stagione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Programmato" si decide per stagione e, dove c'è un'eccezione, per linea. */
class ProgrammatoPerLineaTest extends TestCase
{
    use RefreshDatabase;

    private function prodotto(Stagione $s, Linea $linea, string $codice): Prodotto
    {
        return Prodotto::create(['codice' => $codice, 'nome' => 'Abito', 'prezzo_base' => 50, 'attivo' => true, 'stagione_id' => $s->id, 'linea' => $linea]);
    }

    public function test_senza_eccezioni_vale_la_stagione_per_tutte_le_linee(): void
    {
        $s = Stagione::create(['codice' => 'PE27', 'attiva' => true, 'programmata' => true]);

        $this->assertTrue($this->prodotto($s, Linea::ClaraG, 'A1')->isProgrammato());
        $this->assertTrue($this->prodotto($s, Linea::ClasseDiValentina, 'A2')->isProgrammato());
    }

    public function test_l_eccezione_di_linea_vince_sul_valore_della_stagione(): void
    {
        $s = Stagione::create(['codice' => 'PE27', 'attiva' => true, 'programmata' => true,
            'programmata_linee' => [['linea' => 'CLARA G', 'programmata' => false]]]);

        $this->assertFalse($this->prodotto($s, Linea::ClaraG, 'B1')->isProgrammato());          // eccezione: pronto
        $this->assertTrue($this->prodotto($s, Linea::ClasseDiValentina, 'B2')->isProgrammato()); // resta programmata

        $pronta = Stagione::create(['codice' => 'AI27', 'attiva' => true, 'programmata' => false,
            'programmata_linee' => [['linea' => 'CLASSE DI VALENTINA', 'programmata' => true]]]);

        $this->assertTrue($this->prodotto($pronta, Linea::ClasseDiValentina, 'B3')->isProgrammato());
        $this->assertFalse($this->prodotto($pronta, Linea::Oltretempo, 'B4')->isProgrammato());
    }

    public function test_il_dettaglio_prodotto_espone_il_programmato_della_linea(): void
    {
        $agente = Agente::create([
            'codice_agente' => 'A200', 'nome' => 'Agente', 'email' => 'ag@example.test',
            'password' => 'password-agente', 'listino_default' => ListinoTipo::Standard, 'attivo' => true,
        ]);
        $s = Stagione::create(['codice' => 'PE27', 'attiva' => true, 'programmata' => true,
            'programmata_linee' => [['linea' => 'CLARA G', 'programmata' => false]]]);
        $clara = $this->prodotto($s, Linea::ClaraG, 'C1');
        $classe = $this->prodotto($s, Linea::ClasseDiValentina, 'C2');

        $this->actingAs($agente, 'sanctum')->getJson("/api/products/{$clara->id}")->assertOk()->assertJsonPath('data.programmata', false);
        $this->actingAs($agente, 'sanctum')->getJson("/api/products/{$classe->id}")->assertOk()->assertJsonPath('data.programmata', true);
    }
}
