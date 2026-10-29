<?php

namespace Tests\Feature;

use App\Enums\ListinoTipo;
use App\Models\Agente;
use App\Models\Colore;
use App\Models\Prodotto;
use App\Models\Taglia;
use App\Models\VarianteProdotto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le taglie del dettaglio prodotto escono sempre in ordine (42 → 60), comunque arrivino da WinMino. */
class ProdottoTaglieOrdineTest extends TestCase
{
    use RefreshDatabase;

    public function test_il_dettaglio_prodotto_ordina_le_taglie_per_ordine(): void
    {
        $agente = Agente::create([
            'codice_agente' => 'A100', 'nome' => 'Agente Test', 'email' => 'agente@example.test',
            'password' => 'password-agente', 'listino_default' => ListinoTipo::Standard, 'attivo' => true,
        ]);
        $p = Prodotto::create(['codice' => 'ORD1', 'nome' => 'Maglia', 'prezzo_base' => 20, 'attivo' => true]);
        $colore = Colore::create(['nome' => 'BLU', 'codice' => 'BLU']);

        foreach ([52, 60, 44, 58, 42, 56, 46] as $n) {
            $taglia = Taglia::firstOrCreate(['nome' => (string) $n], ['codice' => (string) $n, 'ordine' => $n]);
            VarianteProdotto::create(['prodotto_id' => $p->id, 'taglia_id' => $taglia->id, 'colore_id' => $colore->id, 'sku' => "ORD1-{$n}", 'prezzo' => 20, 'quantita' => 0]);
        }

        $taglie = collect($this->actingAs($agente, 'sanctum')->getJson("/api/products/{$p->id}")->assertOk()->json('data.varianti'))
            ->pluck('taglia')->all();

        $this->assertSame(['42', '44', '46', '52', '56', '58', '60'], $taglie);
    }
}
