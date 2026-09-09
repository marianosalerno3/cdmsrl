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
 * Shopify Admin REST API.
 *   https://<shop>.myshopify.com/admin/api/<version>/...
 *   header: X-Shopify-Access-Token
 *
 * Mapping opzioni variante: option1 = Taglia, option2 = Colore.
 * NOTA: testabile solo contro un development store reale.
 */
class ShopifyChannel implements EcommerceChannel
{
    private string $base;

    private ?int $locationId = null;

    public function __construct(private readonly IntegrationSettings $settings)
    {
        $domain = trim((string) $settings->shopify_shop_domain);
        $version = config('integrations.shopify.api_version', '2025-01');
        $this->base = "https://{$domain}/admin/api/{$version}";
    }

    public function name(): string
    {
        return 'shopify';
    }

    public function test(): bool
    {
        try {
            return $this->http()->get("{$this->base}/shop.json")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function pushProduct(Prodotto $prodotto): ChannelResult
    {
        $prodotto->loadMissing('varianti.taglia', 'varianti.colore', 'immagini');

        $payload = ['product' => $this->productPayload($prodotto)];

        $response = $prodotto->shopify_product_id
            ? $this->http()->put("{$this->base}/products/{$prodotto->shopify_product_id}.json", $payload)
            : $this->http()->post("{$this->base}/products.json", $payload);

        if ($response->failed()) {
            return ChannelResult::fail("Shopify {$response->status()}: ".$response->body());
        }

        $product = $response->json('product');
        $prodotto->forceFill(['shopify_product_id' => (string) $product['id']])->saveQuietly();

        // riallinea gli id variante per SKU
        $bySku = collect($product['variants'] ?? [])->keyBy('sku');
        foreach ($prodotto->varianti as $v) {
            if ($sv = $bySku->get($v->sku)) {
                $v->forceFill(['shopify_variant_id' => (string) $sv['id']])->saveQuietly();
            }
        }

        return ChannelResult::ok('Prodotto pubblicato su Shopify', ['product_id' => $product['id']]);
    }

    public function pushInventory(Prodotto $prodotto): ChannelResult
    {
        $prodotto->loadMissing('varianti');
        $location = $this->firstLocationId();
        if (! $location) {
            return ChannelResult::fail('Shopify: nessuna location disponibile');
        }

        $updated = 0;
        foreach ($prodotto->varianti as $v) {
            if (blank($v->shopify_variant_id)) {
                continue;
            }
            $variant = $this->http()->get("{$this->base}/variants/{$v->shopify_variant_id}.json")->json('variant');
            $inventoryItemId = $variant['inventory_item_id'] ?? null;
            if (! $inventoryItemId) {
                continue;
            }

            $res = $this->http()->post("{$this->base}/inventory_levels/set.json", [
                'location_id' => $location,
                'inventory_item_id' => $inventoryItemId,
                'available' => (int) $v->quantita,
            ]);
            if ($res->successful()) {
                $updated++;
            }
        }

        return ChannelResult::ok("Giacenze aggiornate su Shopify ({$updated} varianti)");
    }

    public function pullOrders(CarbonInterface $since): array
    {
        $response = $this->http()->get("{$this->base}/orders.json", [
            'status' => 'any',
            'updated_at_min' => $since->toIso8601String(),
            'limit' => 250,
        ]);

        if ($response->failed()) {
            return [];
        }

        return collect($response->json('orders', []))->map(fn ($o) => [
            'external_id' => (string) $o['id'],
            'numero' => $o['name'] ?? ('#'.$o['order_number']),
            'data' => $o['created_at'] ?? null,
            'totale' => (float) ($o['total_price'] ?? 0),
            'cliente_nome' => trim(($o['customer']['first_name'] ?? '').' '.($o['customer']['last_name'] ?? '')),
            'cliente_email' => $o['email'] ?? null,
            'stato' => $o['financial_status'] ?? 'unknown',
            'righe' => collect($o['line_items'] ?? [])->map(fn ($li) => [
                'sku' => $li['sku'] ?? null,
                'nome' => $li['title'] ?? null,
                'quantita' => (int) ($li['quantity'] ?? 0),
                'prezzo' => (float) ($li['price'] ?? 0),
            ])->all(),
        ])->all();
    }

    // ---------------------------------------------------------------------

    private function productPayload(Prodotto $p): array
    {
        return [
            'title' => $p->nome,
            'body_html' => $p->descrizione ?? '',
            'product_type' => $p->categoria?->nome,
            'tags' => collect([$p->stagione?->codice, $p->genere?->nome])->filter()->implode(', '),
            'status' => $p->attivo ? 'active' : 'draft',
            'options' => [['name' => 'Taglia'], ['name' => 'Colore']],
            'variants' => $p->varianti->map(fn (VarianteProdotto $v) => [
                'option1' => $v->taglia?->nome ?: 'UNICA',
                'option2' => $v->colore?->nome ?: 'UNICO',
                'sku' => $v->sku,
                'price' => number_format((float) $v->prezzo, 2, '.', ''),
                'inventory_management' => 'shopify',
                'inventory_quantity' => (int) $v->quantita,
            ])->values()->all(),
            'images' => $p->immagini->map(fn ($i) => ['src' => $i->url])->values()->all(),
        ];
    }

    private function firstLocationId(): ?int
    {
        if ($this->locationId !== null) {
            return $this->locationId;
        }
        $locations = $this->http()->get("{$this->base}/locations.json")->json('locations', []);

        return $this->locationId = $locations[0]['id'] ?? null;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['X-Shopify-Access-Token' => (string) $this->settings->shopify_access_token])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500);
    }
}
