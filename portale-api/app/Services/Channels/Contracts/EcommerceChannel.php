<?php

namespace App\Services\Channels\Contracts;

use App\Models\Prodotto;
use App\Services\Channels\ChannelResult;
use Carbon\CarbonInterface;

/**
 * Canale e-commerce verso cui pubblicare catalogo/giacenze e da cui leggere ordini.
 * Implementazioni: ShopifyChannel, WooCommerceChannel.
 */
interface EcommerceChannel
{
    public function name(): string;

    /** Verifica connettività / credenziali. */
    public function test(): bool;

    /**
     * Crea o aggiorna il prodotto (con varianti e immagini) sul canale.
     * Salva gli id di mapping su prodotti.* e variante_prodotti.*.
     */
    public function pushProduct(Prodotto $prodotto): ChannelResult;

    /** Aggiorna solo le giacenze delle varianti già mappate (più veloce di pushProduct). */
    public function pushInventory(Prodotto $prodotto): ChannelResult;

    /**
     * Ordini del canale creati/aggiornati dopo $since, in forma normalizzata:
     * [{ external_id, numero, data, totale, cliente_nome, cliente_email, stato, righe: [...] }]
     *
     * @return array<int, array<string,mixed>>
     */
    public function pullOrders(CarbonInterface $since): array;
}
