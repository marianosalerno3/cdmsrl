<?php

namespace App\Services\Channels;

use App\Models\Prodotto;
use App\Models\VarianteProdotto;
use App\Services\Channels\Contracts\EcommerceChannel;
use App\Settings\IntegrationSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
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

    /** L'API REST di Shopify accetta al massimo 100 varianti per prodotto; oltre si usa GraphQL (fino a 2048). */
    private const REST_MAX_VARIANTI = 100;

    public function pushProduct(Prodotto $prodotto): ChannelResult
    {
        $prodotto->loadMissing('varianti.taglia', 'varianti.colore', 'immagini');

        // senza varianti Shopify rifiuta il prodotto (422): non ha senso ritentare, lo segnaliamo e basta
        if ($prodotto->varianti->isEmpty()) {
            return ChannelResult::fail("Prodotto {$prodotto->codice} senza varianti (taglie/colori): non inviato a Shopify");
        }

        if ($prodotto->varianti->count() > self::REST_MAX_VARIANTI) {
            return $this->pushProductGraphql($prodotto);
        }

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

        // un prodotto mai inviato lo decide CDM (pulsante "Invia a Shopify"): qui non lo si crea di nascosto
        if (blank($prodotto->shopify_product_id)) {
            return ChannelResult::ok('Prodotto non ancora inviato a Shopify: giacenze non inviate');
        }

        // con tante varianti l'aggiornamento una per una (2 chiamate ciascuna) supererebbe il tempo del job:
        // si rimanda l'intero prodotto con productSet, che aggiorna anche le giacenze in un'unica chiamata
        if ($prodotto->varianti->count() > self::REST_MAX_VARIANTI) {
            return $this->pushProduct($prodotto);
        }

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

    /**
     * Moltiplicatore del prezzo B2C rispetto al listino base dell'ingrosso (impostabile in Configurazioni Sistema).
     * Ogni linea può avere il suo (es. Clara G. ×2,8); senza, vale il ricarico generale in % (300 = ×4, 0 = ×1).
     */
    public function moltiplicatore(Prodotto $p): float
    {
        $perLinea = (array) \App\Models\AppConfig::get('shopify_moltiplicatori_linea', []);
        $linea = $perLinea[$p->linea?->value ?? ''] ?? null;
        if (is_numeric($linea) && (float) $linea > 0) {
            return (float) $linea;
        }

        return 1 + ((float) \App\Models\AppConfig::get('shopify_markup_perc', 0)) / 100;
    }

    private function productPayload(Prodotto $p): array
    {
        $markup = $this->moltiplicatore($p);

        return [
            'title' => $p->nome,
            'body_html' => $this->bodyHtml($p),
            'product_type' => $p->categoria?->nome,
            // Tag usati per le collezioni Shopify per linea/stagione (es. "Linea: Clara G", "PE27").
            'tags' => collect([$p->linea?->value ? "Linea: {$p->linea->value}" : null, $p->stagione?->codice, $p->genere?->nome])->filter()->implode(', '),
            'status' => $p->attivo ? 'active' : 'draft',
            'options' => [['name' => 'Taglia'], ['name' => 'Colore']],
            'variants' => $this->variantiOrdinate($p)->map(fn (VarianteProdotto $v) => [
                'option1' => $v->taglia?->nome ?: 'UNICA',
                'option2' => $v->colore?->nome ?: 'UNICO',
                'sku' => $v->sku,
                'price' => number_format(round(((float) $v->prezzo) * $markup, 2), 2, '.', ''),
                'inventory_management' => 'shopify',
                'inventory_quantity' => (int) $v->quantita,
            ])->values()->all(),
            'images' => $p->immagini->map(fn ($i) => ['src' => $i->url])->values()->all(),
        ];
    }

    /**
     * Varianti in ordine di taglia (42, 44, … 60; poi XS…XXL; poi taglia unica) e, a parità, di colore:
     * Shopify mostra i valori dell'opzione nell'ordine in cui compaiono, e WinMino non li dà ordinati.
     *
     * @return \Illuminate\Support\Collection<int,VarianteProdotto>
     */
    private function variantiOrdinate(Prodotto $p): \Illuminate\Support\Collection
    {
        return $p->varianti
            ->sort(fn (VarianteProdotto $a, VarianteProdotto $b) => [$a->taglia?->ordine ?? 2000, (string) $a->colore?->nome]
                <=> [$b->taglia?->ordine ?? 2000, (string) $b->colore?->nome])
            ->values();
    }

    /**
     * Rimette in ordine (42 → 60) le taglie di un prodotto già presente su Shopify, senza toccare altro.
     */
    public function riordinaTaglie(Prodotto $prodotto): ChannelResult
    {
        if (blank($prodotto->shopify_product_id)) {
            return ChannelResult::ok('Prodotto non ancora inviato a Shopify: niente da riordinare');
        }

        $prodotto->loadMissing('varianti.taglia', 'varianti.colore');
        $taglie = $this->variantiOrdinate($prodotto)
            ->map(fn (VarianteProdotto $v) => $v->taglia?->nome ?: 'UNICA')->unique()->values()
            ->map(fn (string $nome) => ['name' => $nome])->all();

        $mutation = 'mutation($id: ID!, $options: [OptionReorderInput!]!) {
            productOptionsReorder(productId: $id, options: $options) { userErrors { field message code } }
        }';
        $response = $this->http()->post("{$this->base}/graphql.json", [
            'query' => $mutation,
            'variables' => [
                'id' => "gid://shopify/Product/{$prodotto->shopify_product_id}",
                'options' => [['name' => 'Taglia', 'values' => $taglie], ['name' => 'Colore']],
            ],
        ]);

        if ($response->failed()) {
            return ChannelResult::fail("Shopify GraphQL {$response->status()}: ".$response->body());
        }
        $errori = $response->json('errors') ?? $response->json('data.productOptionsReorder.userErrors');
        if (! empty($errori)) {
            return ChannelResult::fail('Shopify riordino taglie: '.json_encode($errori, JSON_UNESCAPED_UNICODE));
        }

        return ChannelResult::ok('Taglie riordinate su Shopify');
    }
    /**
     * Invio via GraphQL `productSet` per i prodotti con più di 100 varianti (limite dell'API REST).
     * Crea o aggiorna prodotto, opzioni Taglia/Colore, varianti con prezzo e giacenza, immagini.
     */
    private function pushProductGraphql(Prodotto $prodotto): ChannelResult
    {
        $p = $this->productPayload($prodotto);
        $location = $this->firstLocationId();

        $valori = fn (string $chiave) => collect($p['variants'])->pluck($chiave)->unique()->values()
            ->map(fn ($v) => ['name' => (string) $v])->all();

        $input = [
            'title' => $p['title'],
            'descriptionHtml' => $p['body_html'],
            'productType' => (string) ($p['product_type'] ?? ''),
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $p['tags'])))),
            'status' => $prodotto->attivo ? 'ACTIVE' : 'DRAFT',
            'productOptions' => [
                ['name' => 'Taglia', 'position' => 1, 'values' => $valori('option1')],
                ['name' => 'Colore', 'position' => 2, 'values' => $valori('option2')],
            ],
            'variants' => collect($p['variants'])->map(fn (array $v) => array_filter([
                'optionValues' => [
                    ['optionName' => 'Taglia', 'name' => (string) $v['option1']],
                    ['optionName' => 'Colore', 'name' => (string) $v['option2']],
                ],
                'sku' => $v['sku'],
                'price' => $v['price'],
                'inventoryItem' => ['tracked' => true],
                'inventoryQuantities' => $location
                    ? [['locationId' => "gid://shopify/Location/{$location}", 'name' => 'available', 'quantity' => (int) $v['inventory_quantity']]]
                    : null,
            ], fn ($x) => $x !== null))->values()->all(),
        ];

        // le immagini si caricano solo alla creazione: ripetendole a ogni aggiornamento si duplicherebbero
        if (! $prodotto->shopify_product_id) {
            $input['files'] = collect($p['images'])->map(fn (array $i) => ['originalSource' => $i['src'], 'contentType' => 'IMAGE'])->values()->all();
        }

        $variables = ['input' => $input];
        $mutation = 'mutation ProdottoSet($input: ProductSetInput!'.($prodotto->shopify_product_id ? ', $identifier: ProductSetIdentifiers' : '').') {
            productSet(input: $input, synchronous: true'.($prodotto->shopify_product_id ? ', identifier: $identifier' : '').') {
                product { id variants(first: 250) { nodes { id sku } } }
                userErrors { field message code }
            }
        }';
        if ($prodotto->shopify_product_id) {
            $variables['identifier'] = ['id' => "gid://shopify/Product/{$prodotto->shopify_product_id}"];
        }

        $response = $this->http()->timeout(120)->post("{$this->base}/graphql.json", ['query' => $mutation, 'variables' => $variables]);

        if ($response->failed()) {
            return ChannelResult::fail("Shopify GraphQL {$response->status()}: ".$response->body());
        }

        $errori = $response->json('errors') ?? $response->json('data.productSet.userErrors');
        if (! empty($errori)) {
            return ChannelResult::fail('Shopify productSet: '.json_encode($errori, JSON_UNESCAPED_UNICODE));
        }

        $product = $response->json('data.productSet.product');
        if (! $product) {
            return ChannelResult::fail('Shopify productSet: risposta senza prodotto');
        }

        $prodotto->forceFill(['shopify_product_id' => $this->idNumerico($product['id'])])->saveQuietly();

        $perSku = collect($product['variants']['nodes'] ?? [])->keyBy('sku');
        foreach ($prodotto->varianti as $v) {
            if ($sv = $perSku->get($v->sku)) {
                $v->forceFill(['shopify_variant_id' => $this->idNumerico($sv['id'])])->saveQuietly();
            }
        }

        return ChannelResult::ok('Prodotto pubblicato su Shopify (GraphQL, '.count($input['variants']).' varianti)', ['product_id' => $product['id']]);
    }

    /** "gid://shopify/Product/123" → "123" */
    private function idNumerico(string $gid): string
    {
        return substr($gid, (int) strrpos($gid, '/') + 1);
    }

    /**
     * Descrizione del prodotto su Shopify: testo web (se c'è) + composizione e tessuto presi da WinMino.
     * La composizione arriva in maiuscolo e senza separatori ("POLIESTERE 95% ELASTOMERO 5%"): la rendiamo leggibile.
     */
    private function bodyHtml(Prodotto $p): string
    {
        $parti = [];

        if (filled($p->descrizione)) {
            $parti[] = '<p>'.nl2br(e(trim((string) $p->descrizione))).'</p>';
        }
        if (filled($p->composizione)) {
            $parti[] = '<p><strong>Composizione:</strong> '.e(self::formattaComposizione((string) $p->composizione)).'</p>';
        }
        if (filled($p->tessuto)) {
            $parti[] = '<p><strong>Tessuto:</strong> '.e(mb_convert_case(mb_strtolower(trim((string) $p->tessuto)), MB_CASE_TITLE)).'</p>';
        }

        return implode("\n", $parti);
    }

    /** "VISCOSA 65% NYLON 32% SPANDEX 3%" → "Viscosa 65%, Nylon 32%, Spandex 3%". */
    public static function formattaComposizione(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match_all('/([^\d%,;]+?)\s*(\d+(?:[.,]\d+)?)\s*%/u', $raw, $m, PREG_SET_ORDER) && $m !== []) {
            return collect($m)
                ->map(fn ($x) => mb_convert_case(mb_strtolower(trim($x[1], " \t-/")), MB_CASE_TITLE).' '.$x[2].'%')
                ->implode(', ');
        }

        return mb_convert_case(mb_strtolower($raw), MB_CASE_TITLE);
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
            ->timeout(60)
            // si ritenta solo ciò che può passare da solo (rete, conflitto, limite di richieste, errori del server):
            // un 422 (dati rifiutati) rimarrebbe tale e fa solo perdere tempo
            ->retry(2, 500, fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && in_array($e->response->status(), [409, 429, 500, 502, 503, 504], true)), throw: false);
    }
}
