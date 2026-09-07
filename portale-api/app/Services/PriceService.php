<?php

namespace App\Services;

use App\Enums\ListinoTipo;
use App\Models\AppConfig;
use App\Models\VarianteProdotto;

/**
 * Regole di prezzo centralizzate.
 *
 * - Prezzo base = VarianteProdotto::$prezzo (listino "standard").
 * - Listino cliente/agente applica un moltiplicatore (App\Enums\ListinoTipo::multiplier()).
 * - IVA: aliquota da AppConfig ("vat", default 22) — calcolata sul totale imponibile.
 * - Spese spedizione: da AppConfig ("spese_spedizione").
 */
class PriceService
{
    public function forVariant(VarianteProdotto $variante, ListinoTipo $listino): float
    {
        return round(((float) $variante->prezzo) * $listino->multiplier(), 2);
    }

    public function vatRate(): float
    {
        return (float) AppConfig::get('vat', (float) env('BIZ_VAT_RATE', 22));
    }

    public function shippingCost(): float
    {
        return (float) AppConfig::get('spese_spedizione', (float) env('BIZ_SHIPPING_COST', 15));
    }

    public function minOrderAmount(): float
    {
        return (float) AppConfig::get('importo_minimo_ordine', (float) env('BIZ_MIN_ORDER_AMOUNT', 0));
    }

    /**
     * Calcola i totali di un ordine.
     *
     * @param  array<int,array{prezzo_unitario:float,quantita:int}>  $righe
     * @return array{subtotale:float,spese_spedizione:float,iva_perc:float,iva_importo:float,totale:float,totale_pezzi:int}
     */
    public function totals(array $righe, ?float $speseSpedizione = null): array
    {
        $subtotale = 0.0;
        $pezzi = 0;

        foreach ($righe as $riga) {
            $subtotale += round($riga['prezzo_unitario'] * $riga['quantita'], 2);
            $pezzi += (int) $riga['quantita'];
        }

        $subtotale = round($subtotale, 2);
        $spedizione = round($speseSpedizione ?? $this->shippingCost(), 2);
        $ivaPerc = $this->vatRate();
        $imponibile = $subtotale + $spedizione;
        $iva = round($imponibile * $ivaPerc / 100, 2);

        return [
            'subtotale' => $subtotale,
            'spese_spedizione' => $spedizione,
            'iva_perc' => $ivaPerc,
            'iva_importo' => $iva,
            'totale' => round($imponibile + $iva, 2),
            'totale_pezzi' => $pezzi,
        ];
    }
}
