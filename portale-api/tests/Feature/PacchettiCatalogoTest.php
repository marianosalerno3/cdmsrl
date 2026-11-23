<?php

namespace Tests\Feature;

use App\Enums\Linea;
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

    private function prodotto(string $codice, ?Pacchetto $pacchetto = null, bool $attivo = true, ?Linea $linea = null): Prodotto
    {
        $p = Prodotto::create([
            'codice' => $codice, 'nome' => 'Abito '.$codice, 'prezzo_base' => 100,
            'attivo' => $attivo, 'pacchetto_id' => $pacchetto?->id, 'linea' => $linea ?? $pacchetto?->linea,
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
        $cerimonia = Pacchetto::create(['nome' => 'Cerimonia', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
        $capsule = Pacchetto::create(['nome' => 'Capsule', 'linea' => Linea::ClasseDiValentina, 'ordine' => 2]);
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
        $cerimonia = Pacchetto::create(['nome' => 'Cerimonia', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
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
        $secondo = Pacchetto::create(['nome' => 'Capsule', 'linea' => Linea::ClasseDiValentina, 'ordine' => 2]);
        $primo = Pacchetto::create(['nome' => 'Cerimonia', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
        $vuoto = Pacchetto::create(['nome' => 'Vuoto', 'linea' => Linea::ClasseDiValentina, 'ordine' => 3]);
        $nascosto = Pacchetto::create(['nome' => 'Nascosto', 'linea' => Linea::ClasseDiValentina, 'ordine' => 4]);
        $this->prodotto('A1', $secondo);
        $this->prodotto('B1', $primo);
        $this->prodotto('C1', $nascosto, attivo: false);

        $this->getJson('/api/filters')->assertOk()->assertExactJson([
            'lines' => array_column(Linea::cases(), 'value'),
            'categories' => [],
            'seasons' => [],
            'packages' => ['Cerimonia', 'Capsule'],
            'packages_by_line' => ['CLASSE DI VALENTINA' => ['Cerimonia', 'Capsule']],
            'fabrics' => [],
        ]);
    }

    public function test_ogni_linea_vede_solo_i_suoi_pacchetti_anche_con_lo_stesso_nome(): void
    {
        $classeBasici = Pacchetto::create(['nome' => 'Basici', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
        $classeSera = Pacchetto::create(['nome' => 'Sera', 'linea' => Linea::ClasseDiValentina, 'ordine' => 2]);
        $claraBasici = Pacchetto::create(['nome' => 'Basici', 'linea' => Linea::ClaraG, 'ordine' => 3]); // stesso nome, altra linea
        $claraEstate = Pacchetto::create(['nome' => 'Estate', 'linea' => Linea::ClaraG, 'ordine' => 4]);
        $this->prodotto('A1', $classeBasici);
        $this->prodotto('B1', $classeSera);
        $this->prodotto('C1', $claraBasici);
        $this->prodotto('D1', $claraEstate);

        $filtri = $this->getJson('/api/filters')->assertOk()->json();
        $this->assertSame(['Basici', 'Sera'], $filtri['packages_by_line']['CLASSE DI VALENTINA']);
        $this->assertSame(['Basici', 'Estate'], $filtri['packages_by_line']['CLARA G']);
        $this->assertSame(['Basici', 'Sera', 'Estate'], $filtri['packages']); // elenco generale: nomi senza doppioni

        // il filtro per nome vale dentro la linea scelta
        $this->assertSame(['A1'], $this->codici('?line=CLASSE DI VALENTINA&package=Basici'));
        $this->assertSame(['C1'], $this->codici('?line=CLARA G&package=Basici'));
        // il pacchetto di un'altra linea non porta niente
        $this->assertSame([], $this->codici('?line=CLASSE DI VALENTINA&package=Estate'));
    }

    public function test_la_migrazione_separa_un_pacchetto_condiviso_tra_due_linee(): void
    {
        // torno allo stato precedente (pacchetti senza linea, nome unico) e ci metto i dati com'erano in produzione
        $migrazione = require database_path('migrations/2026_11_23_000002_add_linea_to_pacchetti.php');
        $migrazione->down();

        $storie = Pacchetto::forceCreate(['nome' => 'ROSSO', 'ordine' => 1]);
        $basici = Pacchetto::forceCreate(['nome' => 'BASICI A COLORI', 'ordine' => 2]);
        $vuoto = Pacchetto::forceCreate(['nome' => 'VUOTO', 'ordine' => 3]);
        $a = $this->prodotto('A1', $storie, linea: Linea::ClasseDiValentina);
        $b = $this->prodotto('B1', $basici, linea: Linea::ClasseDiValentina);
        $c = $this->prodotto('C1', $basici, linea: Linea::ClaraG);

        $migrazione->up();

        $this->assertSame('CLASSE DI VALENTINA', $storie->fresh()->linea->value);
        $this->assertNull($vuoto->fresh()->linea); // vuoto: la linea la sceglie CDM
        $this->assertSame('CLARA G', $c->fresh()->pacchettoPortale->linea->value);
        $this->assertSame('BASICI A COLORI', $c->fresh()->pacchettoPortale->nome);
        $this->assertNotSame($b->fresh()->pacchetto_id, $c->fresh()->pacchetto_id); // ora sono due pacchetti, uno per linea
        $this->assertSame('CLASSE DI VALENTINA', $b->fresh()->pacchettoPortale->linea->value);
        $this->assertSame(4, Pacchetto::count());
    }

    public function test_elenco_e_dettaglio_espongono_il_pacchetto(): void
    {
        $pacchetto = Pacchetto::create(['nome' => 'Cerimonia', 'linea' => Linea::ClasseDiValentina, 'ordine' => 1]);
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
