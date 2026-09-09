# Canali e-commerce (Shopify / WooCommerce)

Push di catalogo/giacenze verso gli shop e import degli ordini.
Le credenziali sono in **Configurazioni Sistema** (pannello, cifrate).

## Componenti

| | |
|---|---|
| `App\Services\Channels\ChannelManager` | risolve i canali abilitati dai settings; `enabled()`, `driver($name)`, `syncGiacenzeAutomatica()` |
| `EcommerceChannel` (contratto) | `pushProduct`, `pushInventory`, `pullOrders`, `test` |
| `ShopifyChannel` | Admin REST API (`/admin/api/<ver>`), header `X-Shopify-Access-Token` |
| `WooCommerceChannel` | REST v3 (`/wp-json/wc/v3`), Basic auth consumer key/secret |
| `ChannelResult` | esito `{success, message, data}` |

## Flussi

| Trigger | Cosa fa |
|---|---|
| Azione riga Prodotto "Invia a Shopify / WooCommerce" | `SyncProdottoEcommerce::dispatch(id, canale, 'full')` |
| `sync:giacenze` (ogni 30') / pulsante "Sincronizza Prodotti e Giacenze" | accoda un job `inventory` per ogni prodotto attivo (`--full` per anagrafica completa, `--channel=`, `--codice=`) |
| Observer `VarianteProdottoObserver` | se **sync giacenze automatica** è ON, ogni modifica di `quantita` accoda un push `inventory` |
| `orders:pull-ecommerce` (ogni 15') | importa gli ordini dei canali in `ordini_b2b` (dedup su `shopify_order_id` / `woocommerce_order_id`) → tab "Ordini Shopify" |

## Mapping prodotto

- **Opzioni variante**: `option1 = Taglia`, `option2 = Colore` (Shopify); attributi `Taglia`/`Colore` con `variation:true` (Woo).
- Id di mapping salvati su `prodotti.shopify_product_id` / `woocommerce_product_id` e
  `variante_prodotti.shopify_variant_id` / `woocommerce_variation_id` (riallineati per SKU).
- Prezzo: `variante_prodotti.prezzo` (listino base). Giacenza: `quantita`.

## Da verificare con store reali

- Shopify: aggiornamento stock via `inventory_levels/set` richiede una **location** —
  si usa la prima restituita da `/locations.json`. Confermare su multi-location.
- WooCommerce: le variazioni si creano in batch (`/variations/batch`); verificare
  limiti di `per_page` e paginazione ordini.
- Normalizzazione ordini e-commerce: manca il match col `cliente_id` interno
  (ora si salva solo `cliente_nome`/`email` + payload grezzo in `wordpress_payload`).
