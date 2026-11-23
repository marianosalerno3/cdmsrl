<?php

namespace Tests\Feature;

use App\Enums\ListinoTipo;
use App\Models\Agente;
use App\Models\Pacchetto;
use App\Models\Prodotto;
use App\Models\VarianteProdotto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * I pacchetti li crea CDM nel pannello e si assegnano ai prodotti: nel catalogo servono a filtrare
 * e a ordinare (prima i pacchetti nell'ordine scelto, in fondo i prodotti senza pacchetto).
 */
class PacchettiCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private ?Agente $agente = null;

    private function agente(): Agente
    {
        return $this->agente ??= Agente::create([
            'codice_agente' => 'A400', 'nome' => 'Agente', 'email' => 'ag-pacchetti@example.test',
            'password' => 'password-agente', 'listino_default' => ListinoTipo::Standard, 'attivo' => true,
        ]);
    }

    private function prodotto(string $codice, ?Pacchetto $pacchetto = null, bool $attivo = true): Prodotto
    {
        $p = Prodotto::create([
            'codice' => $codice, 'nome' => 'Abito '.$codice, 'prezzo_base' => 100,
            'attivo' => $attivo, 'pacchetto_id' => $pacchetto?->id,
        ]);
        VarianteProdotto::create(['prodotto_id' => $p->id, 'sku' => 'SKU'.$codice, 'prezzo' => 100, 'quantita' => 5]);

        return $p;
    }

    private function codici(string $query = ''): array
    {
        return collect($this->actingAs($this->agente(), 'sanctum')->getJson('/api/products'.$query)->assertOk()->json('data'))
            ->pluck('codice')->all();
    }

    public function test_ordinamento_per_pacchetto_segue_l_ordine_scelto_e_mette_in_fondo_i_senza_pacchetto(): void
    {
        $cerimonia = Pacchetto::create(['nome' => 'Cerimonia', 'ordine' => 1]);
        $capsule = Pacchetto::create(['nome' => 'Capsule', 'ordine' => 2]);
        $this->prodotto('A1', $capsule);
        $this->prodotto('B1');            // senza pacchetto
        $this->prodotto('C1', $cerimonia);
        $this->prodotto('D1', $capsule);
        $this->prodotto('E1', $cerimonia);

        // di default si ordina per codice, come prima
        $this->assertSame(['A1', 'B1', 'C1', 'D1', 'E1'], $this->codici());
        // per pacchetto: Cerimonia (1), Capsule (2), poi gli altri; dentro ogni gruppo per codice
        $this->assertSame(['C1', 'E1', 'A1', 'D1', 'B1'], $this->codici('?sort=pacchetto'));
    }

    public function test_si_puo_filtrare_per_pacchetto_insieme_alla_ricerca(): void
    {
        $cerimonia = Pacchetto::create(['nome' => 'Cerimonia', 'ordine' => 1]);
        $this->prodotto('A1');
        $this->prodotto('C1', $cerimonia);
        $this->prodotto('E1', $cerimonia);

        $this->assertSame(['C1', 'E1'], $this->codici('?package=Cerimonia'));
        // con la ricerca per nome la colonna `nome` non deve risultare ambigua con quella dei pacchetti
        $this->assertSame(['E1'], $this->codici('?package=Cerimonia&search=E1&sort=pacchetto'));
        $this->assertSame([], $this->codici('?package=Inesistente'));
    }

    public function test_l_elenco_filtri_ha_i_pacchetti_in_ordine_e_solo_quelli_con_prodotti_pubblicati(): void
    {
        $secondo = Pacchetto::create(['nome' => 'Capsule', 'ordine' => 2]);
        $primo = Pacchetto::create(['nome' => 'Cerimonia', 'ordine' => 1]);
        $vuoto = Pacchetto::create(['nome' => 'Vuoto', 'ordine' => 3]);
        $nascosto = Pacchetto::create(['nome' => 'Nascosto', 'ordine' => 4]);
        $this->prodotto('A1', $secondo);
        $this->prodotto('B1', $primo);
        $this->prodotto('C1', $nascosto, attivo: false);

        $this->getJson('/api/filters')->assertOk()->assertExactJson([
            'lines' => array_column(\App\Enums\Linea::cases(), 'value'),
            'categories' => [],
            'seasons' => [],
            'packages' => ['Cerimonia', 'Capsule'],
            'fabrics' => [],
        ]);
    }

    public function test_elenco_e_dettaglio_espongono_il_pacchetto(): void
    {
        $pacchetto = Pacchetto::create(['nome' => 'Cerimonia', 'ordine' => 1]);
        $con = $this->prodotto('A1', $pacchetto);
        $senza = $this->prodotto('B1');
        $agente = $this->agente();

        $righe = collect($this->actingAs($agente, 'sanctum')->getJson('/api/products')->assertOk()->json('data'))->keyBy('codice');
        $this->assertSame('Cerimonia', $righe['A1']['pacchetto']);
        $this->assertNull($righe['B1']['pacchetto']);

        $this->actingAs($agente, 'sanctum')->getJson("/api/products/{$con->id}")
            ->assertOk()->assertJsonPath('data.pacchetto', 'Cerimonia');
        $this->actingAs($agente, 'sanctum')->getJson("/api/products/{$senza->id}")
            ->assertOk()->assertJsonPath('data.pacchetto', null);
    }
}
