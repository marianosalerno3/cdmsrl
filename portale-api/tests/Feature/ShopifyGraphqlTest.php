<?php

namespace Tests\Feature;

use App\Models\Colore;
use App\Models\Prodotto;
use App\Models\Taglia;
use App\Models\VarianteProdotto;
use App\Services\Channels\ShopifyChannel;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyGraphqlTest extends TestCase
{
    use RefreshDatabase;

    private function canale(): ShopifyChannel
    {
        $settings = app(IntegrationSettings::class);
        $settings->shopify_shop_domain = 'test.myshopify.com';
        $settings->shopify_access_token = 'token-di-test';

        return new ShopifyChannel($settings);
    }

    /** Prodotto con taglie x colori varianti. */
    private function prodotto(int $taglie, int $colori, ?string $shopifyId = null): Prodotto
    {
        $p = Prodotto::create(['codice' => 'P'.random_int(1000, 9999), 'nome' => 'Abito test', 'prezzo_base' => 100, 'attivo' => true, 'shopify_product_id' => $shopifyId]);
        $listaColori = collect(range(1, $colori))->map(fn ($c) => Colore::create(['nome' => "Colore $c", 'codice' => "C$c".random_int(100, 999)]));

        foreach (range(1, $taglie) as $t) {
            $taglia = Taglia::create(['nome' => (string) (36 + $t), 'codice' => "T$t".random_int(100, 999), 'ordine' => $t]);
            foreach ($listaColori as $colore) {
                VarianteProdotto::create([
                    'prodotto_id' => $p->id, 'taglia_id' => $taglia->id, 'colore_id' => $colore->id,
                    'sku' => $p->codice.'-'.$taglia->id.'-'.$colore->id, 'prezzo' => 100, 'quantita' => 2,
                ]);
            }
        }

        return $p->fresh();
    }

    private function rispostaProductSet(Prodotto $p, string $productId = '999'): array
    {
        return ['data' => ['productSet' => [
            'product' => [
                'id' => "gid://shopify/Product/$productId",
                'variants' => ['nodes' => $p->varianti->values()->map(fn ($v, $i) => ['id' => 'gid://shopify/ProductVariant/'.(5000 + $i), 'sku' => $v->sku])->all()],
            ],
            'userErrors' => [],
        ]]];
    }

    public function test_un_prodotto_senza_varianti_non_viene_inviato(): void
    {
        Http::fake();
        $p = Prodotto::create(['codice' => 'VUOTO1', 'nome' => 'Senza varianti', 'prezzo_base' => 10, 'attivo' => true]);

        $res = $this->canale()->pushProduct($p);

        $this->assertFalse($res->success);
        $this->assertStringContainsString('senza varianti', $res->message);
        Http::assertNothingSent();
    }

    public function test_oltre_100_varianti_usa_graphql_product_set(): void
    {
        $p = $this->prodotto(12, 10); // 120 varianti
        Http::fake([
            '*/graphql.json' => Http::response($this->rispostaProductSet($p, '777')),
            '*/locations.json' => Http::response(['locations' => [['id' => 42]]]),
        ]);

        $res = $this->canale()->pushProduct($p);

        $this->assertTrue($res->success, $res->message);

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/graphql.json')) {
                return false;
            }
            $input = $r['variables']['input'];

            return $r->hasHeader('X-Shopify-Access-Token', 'token-di-test')
                && str_contains($r['query'], 'productSet')
                && count($input['variants']) === 120
                && $input['productOptions'][0]['name'] === 'Taglia'
                && count($input['productOptions'][0]['values']) === 12
                && count($input['productOptions'][1]['values']) === 10
                && $input['variants'][0]['inventoryQuantities'][0]['locationId'] === 'gid://shopify/Location/42'
                && $input['variants'][0]['inventoryQuantities'][0]['quantity'] === 2
                && $input['variants'][0]['price'] === '100.00'
                && ! isset($r['variables']['identifier']); // nuovo prodotto: nessun identificativo
        });

        $p->refresh();
        $this->assertSame('777', $p->shopify_product_id);
        $this->assertSame(120, $p->varianti()->whereNotNull('shopify_variant_id')->count());
    }

    public function test_aggiornamento_graphql_usa_identifier_e_non_ricarica_le_immagini(): void
    {
        $p = $this->prodotto(11, 10, '777'); // 110 varianti, già su Shopify
        Http::fake([
            '*/graphql.json' => Http::response($this->rispostaProductSet($p, '777')),
            '*/locations.json' => Http::response(['locations' => [['id' => 42]]]),
        ]);

        $this->assertTrue($this->canale()->pushProduct($p)->success);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/graphql.json')
            && $r['variables']['identifier'] === ['id' => 'gid://shopify/Product/777']
            && ! isset($r['variables']['input']['files']));
    }

    public function test_gli_errori_di_shopify_vengono_riportati(): void
    {
        $p = $this->prodotto(11, 10);
        Http::fake([
            '*/graphql.json' => Http::response(['data' => ['productSet' => ['product' => null, 'userErrors' => [['field' => ['input', 'title'], 'message' => 'Titolo non valido', 'code' => 'INVALID']]]]]),
            '*/locations.json' => Http::response(['locations' => [['id' => 42]]]),
        ]);

        $res = $this->canale()->pushProduct($p);

        $this->assertFalse($res->success);
        $this->assertStringContainsString('Titolo non valido', $res->message);
        $this->assertNull($p->fresh()->shopify_product_id);
    }

    public function test_le_giacenze_non_creano_di_nascosto_un_prodotto_mai_inviato(): void
    {
        Http::fake();
        $p = $this->prodotto(11, 10); // 110 varianti, mai inviato

        $res = $this->canale()->pushInventory($p);

        $this->assertTrue($res->success);
        Http::assertNothingSent();
    }

    /** Prodotto con le taglie create in ordine sparso, come arrivano da WinMino. */
    private function prodottoConTaglieSparse(?string $shopifyId = null): Prodotto
    {
        $p = Prodotto::create(['codice' => 'S'.random_int(1000, 9999), 'nome' => 'Maglia', 'prezzo_base' => 20, 'attivo' => true, 'shopify_product_id' => $shopifyId]);
        $colore = Colore::firstOrCreate(['nome' => 'BLU'], ['codice' => 'BLU']);
        foreach ([52, 54, 56, 60, 58, 42, 44, 46, 48, 50] as $n) {
            $taglia = Taglia::firstOrCreate(['nome' => (string) $n], ['codice' => (string) $n, 'ordine' => $n]);
            VarianteProdotto::create(['prodotto_id' => $p->id, 'taglia_id' => $taglia->id, 'colore_id' => $colore->id, 'sku' => "{$p->codice}-{$n}", 'prezzo' => 20, 'quantita' => 1]);
        }

        return $p->fresh();
    }

    public function test_le_taglie_vanno_a_shopify_in_ordine_da_42_a_60(): void
    {
        Http::fake(['*/products.json' => Http::response(['product' => ['id' => 55, 'variants' => []]])]);

        $this->assertTrue($this->canale()->pushProduct($this->prodottoConTaglieSparse())->success);

        Http::assertSent(function (Request $r) {
            $taglie = collect($r['product']['variants'])->pluck('option1')->unique()->values()->all();

            return $taglie === ['42', '44', '46', '48', '50', '52', '54', '56', '58', '60'];
        });
    }

    public function test_riordina_le_taglie_di_un_prodotto_gia_su_shopify(): void
    {
        Http::fake(['*/graphql.json' => Http::response(['data' => ['productOptionsReorder' => ['userErrors' => []]]])]);

        $res = $this->canale()->riordinaTaglie($this->prodottoConTaglieSparse('777'));

        $this->assertTrue($res->success, $res->message);
        Http::assertSent(function (Request $r) {
            $opzioni = $r['variables']['options'];

            return $r['variables']['id'] === 'gid://shopify/Product/777'
                && $opzioni[0]['name'] === 'Taglia'
                && array_column($opzioni[0]['values'], 'name') === ['42', '44', '46', '48', '50', '52', '54', '56', '58', '60']
                && $opzioni[1] === ['name' => 'Colore'];
        });

        // un prodotto mai inviato non viene toccato
        Http::fake();
        $this->assertTrue($this->canale()->riordinaTaglie($this->prodottoConTaglieSparse())->success);
        Http::assertNothingSent();
    }

    public function test_fino_a_100_varianti_resta_sul_rest(): void
    {
        $p = $this->prodotto(10, 10); // 100 varianti: limite incluso
        Http::fake([
            '*/products.json' => Http::response(['product' => ['id' => 55, 'variants' => []]]),
        ]);

        $this->assertTrue($this->canale()->pushProduct($p)->success);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/products.json'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/graphql.json'));
    }

    public function test_un_errore_422_non_viene_ritentato(): void
    {
        $p = $this->prodotto(2, 2);
        Http::fake(['*/products.json' => Http::response(['errors' => 'rifiutato'], 422)]);

        $res = $this->canale()->pushProduct($p);

        $this->assertFalse($res->success);
        Http::assertSentCount(1);
    }
}
