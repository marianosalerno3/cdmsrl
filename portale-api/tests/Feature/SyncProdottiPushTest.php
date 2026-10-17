<?php

namespace Tests\Feature;

use App\Jobs\SyncProdottoEcommerce;
use App\Models\Prodotto;
use App\Services\Channels\ChannelManager;
use App\Services\Erp\Contracts\ErpDriver;
use App\Services\Erp\ErpManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Dopo l'import orario (sync:prodotti --push) verso Shopify va solo ciò che è cambiato:
 * giacenza → push delle sole giacenze; prodotto nuovo o con dati/prezzi cambiati → push completo.
 */
class SyncProdottiPushTest extends TestCase
{
    use RefreshDatabase;

    private array $esistenza = ['ROSSO|44' => 5, 'ROSSO|46' => 3];

    private float $prezzo = 20.0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['winmino.import.selezioni' => ['CG:PE27:CLARAG'], 'winmino.import.depositi' => ['DG']]);

        $driver = Mockery::mock(ErpDriver::class);
        $driver->shouldReceive('getStagioni', 'getGruppiMerceologici', 'getPacchetti', 'getColori')->andReturn([]);
        $driver->shouldReceive('getArticoli')->andReturn([
            ['CODICE' => 'ART1', 'DESCRIZIONE' => 'Abito', 'CODLINEA' => 'CG', 'CODSTAGIONE' => 'PE27'],
        ]);
        $driver->shouldReceive('getListiniPrezzi')->andReturnUsing(fn () => [
            ['CODLISTINO' => 'CLARAG', 'CODARTICOLO' => 'ART1', 'PREZZO' => $this->prezzo],
        ]);
        $driver->shouldReceive('getBarcodeTaglieColori')->andReturn([
            ['CODCOLORE' => 'ROSSO', 'TAGLIA' => '44', 'BARCODE' => '111'],
            ['CODCOLORE' => 'ROSSO', 'TAGLIA' => '46', 'BARCODE' => '222'],
        ]);
        $driver->shouldReceive('getGiacenze')->andReturnUsing(function () {
            $rows = [];
            foreach ($this->esistenza as $k => $q) {
                [$colore, $taglia] = explode('|', $k);
                $rows[] = ['CODDEPOSITO' => 'DG', 'CODCOLORE' => $colore, 'TAGLIA' => $taglia, 'ESISTENZA' => $q, 'IMPEGNATA' => 0];
            }

            return $rows;
        });

        $erp = Mockery::mock(ErpManager::class);
        $erp->shouldReceive('isConfigured')->andReturn(true);
        $erp->shouldReceive('driver')->andReturn($driver);
        $this->app->instance(ErpManager::class, $erp);

        $canali = Mockery::mock(ChannelManager::class);
        $canali->shouldReceive('enabled')->andReturn(collect(['shopify']));
        $this->app->instance(ChannelManager::class, $canali);

        $this->svuotaCoda();
    }

    /** i job sono unici: senza svuotare i lock un secondo push dello stesso prodotto verrebbe scartato. */
    private function svuotaCoda(): void
    {
        Queue::fake();

        // Cache::flush() non libera i lock dello store "array": si rilasciano quelli dei job unici di ogni prodotto
        foreach (Prodotto::pluck('id') as $id) {
            foreach (['full', 'inventory'] as $mode) {
                Cache::lock('laravel_unique_job:'.SyncProdottoEcommerce::class.":{$id}:all:{$mode}")->forceRelease();
            }
        }
    }

    private function importa(): void
    {
        $this->artisan('sync:prodotti', ['--push' => true])->assertSuccessful();
    }

    private function accodati(string $mode): int
    {
        return Queue::pushed(SyncProdottoEcommerce::class, fn ($j) => $j->mode === $mode)->count();
    }

    public function test_un_prodotto_mai_inviato_non_parte_da_solo_poi_solo_le_giacenze_che_cambiano(): void
    {
        // 1° import: prodotto nuovo, mai inviato a Shopify da CDM → non si accoda niente
        $this->importa();
        Queue::assertNothingPushed();

        // cambia una giacenza: sempre niente, finché CDM non lo manda su Shopify
        $this->esistenza['ROSSO|44'] = 9;
        $this->importa();
        Queue::assertNothingPushed();

        // CDM lo invia a Shopify (simulato)
        Prodotto::where('codice', 'ART1')->update(['shopify_product_id' => '999']);
        $this->svuotaCoda();

        // 2° import senza novità: non si accoda niente
        $this->importa();
        Queue::assertNothingPushed();

        // cambia solo una giacenza: solo giacenze, non il prodotto completo
        $this->esistenza['ROSSO|44'] = 4;
        $this->importa();
        $this->assertSame(1, $this->accodati('inventory'));
        $this->assertSame(0, $this->accodati('full'));
    }

    public function test_un_cambio_di_prezzo_rimanda_il_prodotto_completo(): void
    {
        $this->importa();
        Prodotto::where('codice', 'ART1')->update(['shopify_product_id' => '999']);
        $this->svuotaCoda();

        $this->prezzo = 25.0;
        $this->importa();

        $this->assertSame(1, $this->accodati('full'));
        $this->assertSame(0, $this->accodati('inventory'));
    }

    public function test_un_prodotto_disattivato_non_viene_inviato(): void
    {
        $this->importa();
        Prodotto::where('codice', 'ART1')->update(['shopify_product_id' => '999', 'attivo' => false]);
        $this->svuotaCoda();

        $this->esistenza['ROSSO|44'] = 1;
        $this->importa();

        Queue::assertNothingPushed();
    }
}
