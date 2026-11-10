<?php

namespace Tests\Feature;

use App\Models\Prodotto;
use App\Services\Channels\ShopifyChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ShopifyDescrizioneTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_composizione_di_winmino_diventa_leggibile(): void
    {
        $this->assertSame('Poliestere 95%, Elastomero 5%', ShopifyChannel::formattaComposizione('POLIESTERE 95% ELASTOMERO 5%'));
        $this->assertSame('Viscosa 65%, Nylon 32%, Spandex 3%', ShopifyChannel::formattaComposizione('VISCOSA 65% NYLON 32% SPANDEX 3%'));
        $this->assertSame('Lyocell 100%', ShopifyChannel::formattaComposizione('LYOCELL 100%'));
        $this->assertSame('Cotone 97,5%, Elastomero 2,5%', ShopifyChannel::formattaComposizione('COTONE 97,5% ELASTOMERO 2,5%'));
        // formato inatteso: nessuna perdita di informazione
        $this->assertSame('Seta Pura', ShopifyChannel::formattaComposizione('SETA PURA'));
    }

    public function test_la_descrizione_su_shopify_include_composizione_e_testo_web(): void
    {
        $canale = app(ShopifyChannel::class);
        $body = new ReflectionMethod($canale, 'bodyHtml');
        $body->setAccessible(true);

        $solo = new Prodotto(['codice' => 'X1', 'nome' => 'Giacca', 'composizione' => 'LYOCELL 100%']);
        $this->assertSame('<p><strong>Composizione:</strong> Lyocell 100%</p>', $body->invoke($canale, $solo));

        $completo = new Prodotto([
            'codice' => 'X2', 'nome' => 'Abito', 'descrizione' => "Linea morbida.\nScollo a V <b>", 'composizione' => 'VISCOSA 65% NYLON 35%', 'tessuto' => 'CREPE',
        ]);
        $html = $body->invoke($canale, $completo);
        $this->assertStringContainsString('<p>Linea morbida.<br />', $html);
        $this->assertStringContainsString('Scollo a V &lt;b&gt;', $html); // il testo viene sempre scappato
        $this->assertStringContainsString('<strong>Composizione:</strong> Viscosa 65%, Nylon 35%', $html);
        $this->assertStringContainsString('<strong>Tessuto:</strong> Crepe', $html);

        $this->assertSame('', $body->invoke($canale, new Prodotto(['codice' => 'X3', 'nome' => 'Vuoto'])));
    }
}
