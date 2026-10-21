<?php

namespace Tests\Feature;

use App\Models\AppConfig;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeseSpedizioneTest extends TestCase
{
    use RefreshDatabase;

    public function test_sotto_soglia_spesa_fissa(): void
    {
        $this->assertSame(10.0, app(PriceService::class)->shippingFor(299.99, false));
    }

    public function test_dalla_soglia_in_su_il_3_per_cento_arrotondato_per_eccesso(): void
    {
        $prices = app(PriceService::class);

        $this->assertSame(10.0, $prices->shippingFor(300.0, false));  // 3% = 9,00 → non scende sotto i 10 € fissi
        $this->assertSame(10.0, $prices->shippingFor(333.0, false));  // 3% = 9,99 → 10
        $this->assertSame(11.0, $prices->shippingFor(334.0, false));  // 3% = 10,02 → 11
        $this->assertSame(16.0, $prices->shippingFor(525.0, false));  // 3% = 15,75 → 16
        $this->assertSame(16.0, $prices->shippingFor(520.01, false)); // 3% = 15,60 → 16
        $this->assertSame(30.0, $prices->shippingFor(1000.0, false)); // 3% = 30,00 (già intero)
        $this->assertSame(31.0, $prices->shippingFor(1000.50, false)); // 3% = 30,015 → 30,02 → 31
    }

    public function test_la_spesa_non_diminuisce_mai_al_crescere_dell_ordine(): void
    {
        $prices = app(PriceService::class);
        $prima = 0.0;

        for ($totale = 50.0; $totale <= 2000.0; $totale += 0.5) {
            $spesa = $prices->shippingFor($totale, false);
            $this->assertGreaterThanOrEqual($prima, $spesa, "la spesa cala a {$totale} €");
            $prima = $spesa;
        }
    }

    public function test_percentuale_configurabile_e_ordini_programmati_senza_spese(): void
    {
        AppConfig::put('spedizione_pronto_perc', 5.0);

        $this->assertSame(27.0, app(PriceService::class)->shippingFor(525.0, false)); // 26,25 → 27
        $this->assertSame(0.0, app(PriceService::class)->shippingFor(525.0, true));
    }
}
