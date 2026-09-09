<?php

namespace App\Services\Channels;

use App\Models\Prodotto;
use App\Models\VarianteProdotto;
use App\Services\Channels\Contracts\EcommerceChannel;
use App\Settings\IntegrationSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * WooCommerce REST API v3.
 *   <site>/wp-json/wc/v3/...
 *   auth: HTTP Basic (consumer_key : consumer_secret)
 *
 * Prodotto "variable": prodotto padre con attributi Taglia/Colore + una
 * variation per ogni coppia. NOTA: testabile solo contro un sito WooCommerce reale.
 */
class WooCommerceChannel implements EcommerceChannel
{
    private string $base;

    public function __construct(private readonly IntegrationSettings $settings)
    {
        $this->base = rtrim((string) $settings->woocommerce_url, '/').'/wp-json/wc/v3';
    }

    public function name(): string
    {
        return 'woocommerce';
    }

    public function test(): bool
    {
        try {
            return $this->http()->get("{$this->base}/system_status")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function pushProduct(Prodotto $prodotto): ChannelResult
    {
        $prodotto->loadMissing('varianti.taglia', 'varianti.colore', 'immagini');

        $taglie = $prodotto->varianti->map(fn ($v) => $v->taglia?->nome ?: 'UNICA')->unique()->values()->all();
        $colori = $prodotto->varianti->map(fn ($v) => $v->colore?->nome ?: 'UNICO')->unique()->values()->all();

        $parent = [
            'name' => $prodotto->nome,
            'type' => 'variable',
            'sku' => $prodotto->codice,
            'status' => $prodotto->attivo ? 'publish' : 'draft',
            'description' => $prodotto->descrizione ?? '',
            'categories' => $prodotto->categoria ? [['name' => $prodotto->categoria->nome]] : [],
            'images' => $prodotto->immagini->map(fn ($i) => ['src' => $i->url])->values()->all(),
            'attributes' => [
                ['name' => 'Taglia', 'visible' => true, 'variation' => true, 'options' => $taglie],
                ['name' => 'Colore', 'visible' => true, 'variation' => true, 'options' => $colori],
            ],
        ];

        $response = $prodotto->woocommerce_product_id
            ? $this->http()->put("{$this->base}/products/{$prodotto->woocommerce_product_id}", $parent)
            : $this->http()->post("{$this->base}/products", $parent);

        if ($response->failed()) {
            return ChannelResult::fail("WooCommerce {$response->status()}: ".$response->body());
        }

        $productId = $response->json('id');
        $prodotto->forceFill(['woocommerce_product_id' => $productId])->saveQuietly();

        // varianti in batch
        $create = [];
        $update = [];
        foreach ($prodotto->varianti as $v) {
            $body = $this->variationPayload($v);
            $v->woocommerce_variation_id
                ? $update[] = $body + ['id' => $v->woocommerce_variation_id]
                : $create[] = $body;
        }

        $batch = $this->http()->post("{$this->base}/products/{$productId}/variations/batch", [
            'create' => $create,
            'update' => $update,
        ]);

        if ($batch->successful()) {
            $bySku = collect($batch->json('create', []))->merge($batch->json('update', []))->keyBy('sku');
            foreach ($prodotto->varianti as $v) {
                if ($wv = $bySku->get($v->sku)) {
                    $v->forceFill(['woocommerce_variation_id' => $wv['id']])->saveQuietly();
                }
            }
        }

        return ChannelResult::ok('Prodotto pubblicato su WooCommerce', ['product_id' => $productId]);
    }

    public function pushInventory(Prodotto $prodotto): ChannelResult
    {
        $prodotto->loadMissing('varianti');
        if (blank($prodotto->woocommerce_product_id)) {
            return ChannelResult::fail('WooCommerce: prodotto non ancora pubblicato');
        }

        $update = $prodotto->varianti
            ->filter(fn ($v) => filled($v->woocommerce_variation_id))
            ->map(fn ($v) => [
                'id' => $v->woocommerce_variation_id,
                'manage_stock' => true,
                'stock_quantity' => (int) $v->quantita,
            ])->values()->all();

        if (! $update) {
            return ChannelResult::ok('Nessuna variante mappata');
        }

        $res = $this->http()->post("{$this->base}/products/{$prodotto->woocommerce_product_id}/variations/batch", [
            'update' => $update,
        ]);

        return $res->successful()
            ? ChannelResult::ok('Giacenze aggiornate su WooCommerce ('.count($update).' varianti)')
            : ChannelResult::fail("WooCommerce {$res->status()}: ".$res->body());
    }

    public function pullOrders(CarbonInterface $since): array
    {
        $response = $this->http()->get("{$this->base}/orders", [
            'modified_after' => $since->toIso8601String(),
            'per_page' => 100,
        ]);

        if ($response->failed()) {
            return [];
        }

        return collect($response->json())->map(fn ($o) => [
            'external_id' => (string) $o['id'],
            'numero' => $o['number'] ?? (string) $o['id'],
            'data' => $o['date_created'] ?? null,
            'totale' => (float) ($o['total'] ?? 0),
            'cliente_nome' => trim(($o['billing']['first_name'] ?? '').' '.($o['billing']['last_name'] ?? '')),
            'cliente_email' => $o['billing']['email'] ?? null,
            'stato' => $o['status'] ?? 'unknown',
            'righe' => collect($o['line_items'] ?? [])->map(fn ($li) => [
                'sku' => $li['sku'] ?? null,
                'nome' => $li['name'] ?? null,
                'quantita' => (int) ($li['quantity'] ?? 0),
                'prezzo' => (float) ($li['price'] ?? 0),
            ])->all(),
        ])->all();
    }

    // ---------------------------------------------------------------------

    private function variationPayload(VarianteProdotto $v): array
    {
        return [
            'sku' => $v->sku,
            'regular_price' => number_format((float) $v->prezzo, 2, '.', ''),
            'manage_stock' => true,
            'stock_quantity' => (int) $v->quantita,
            'attributes' => [
                ['name' => 'Taglia', 'option' => $v->taglia?->nome ?: 'UNICA'],
                ['name' => 'Colore', 'option' => $v->colore?->nome ?: 'UNICO'],
            ],
        ];
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) $this->settings->woocommerce_consumer_key,
            (string) $this->settings->woocommerce_consumer_secret,
        )->acceptJson()->timeout(30)->retry(2, 500);
    }
}
