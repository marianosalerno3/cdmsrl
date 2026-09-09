# Canale e-commerce — Shopify (B2C)

Push di catalogo/giacenze verso lo shop Shopify e import degli ordini B2C.
Le credenziali sono in **Configurazioni Sistema** (pannello, cifrate).

> Per CDM il canale B2B è **il portale stesso** (gli agenti ordinano lì): l'unico
> canale e-commerce in uscita è Shopify. `ChannelManager` resta generico —
> aggiungere un altro canale = una nuova classe che implementa `EcommerceChannel`.

## Componenti

| | |
|---|---|
| `App\Services\Channels\ChannelManager` | canali abilitati dai settings: `enabled()`, `driver('shopify')`, `syncGiacenzeAutomatica()` |
| `EcommerceChannel` (contratto) | `pushProduct`, `pushInventory`, `pullOrders`, `test` |
| `ShopifyChannel` | Admin REST API (`/admin/api/<ver>`), header `X-Shopify-Access-Token` |
| `ChannelResult` | esito `{success, message, data}` |

## Flussi

| Trigger | Cosa fa |
|---|---|
| `sync:prodotti --push` (ogni ora) | import da WinMino → poi `sync:giacenze --full` |
| Azione riga Prodotto "Invia a Shopify" | `SyncProdottoEcommerce::dispatch(id, 'shopify', 'full')` |
| `sync:giacenze` (ogni 30') / pulsante "Sincronizza Prodotti e Giacenze" | accoda un job `inventory` per ogni prodotto attivo (`--full` per anagrafica completa, `--codice=`) |
| Observer `VarianteProdottoObserver` | se **sync giacenze automatica** è ON, ogni modifica di `quantita` accoda un push `inventory` |
| `orders:pull-ecommerce` (ogni 15') | importa gli ordini Shopify in `ordini_b2b` (dedup su `shopify_order_id`) → tab "Ordini Shopify" |

## Mapping prodotto

- **Opzioni variante**: `option1 = Taglia`, `option2 = Colore`.
- Id di mapping su `prodotti.shopify_product_id` e `variante_prodotti.shopify_variant_id`
  (riallineati per SKU dopo ogni push).
- Prezzo: `variante_prodotti.prezzo` (listino base importato da WinMino). Giacenza: `quantita`.

## Da verificare con lo store reale

- Aggiornamento stock via `inventory_levels/set` richiede una **location** —
  si usa la prima restituita da `/locations.json`. Confermare su multi-location.
- Paginazione ordini (`limit`, `since_id` / `page_info`) per volumi alti.
- Match `cliente_id` interno per gli ordini importati (ora si salva solo
  `cliente_nome`/`email` + payload grezzo in `ecommerce_payload`).
