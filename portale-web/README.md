# portale-web — SPA Agenti B2B (Vue 3 + Vite + Tailwind CSS v4)

Frontend del portale: gli agenti accedono, selezionano un cliente del proprio
portafoglio, navigano il catalogo per stagione, compongono l'ordine con la
matrice taglie/colori, scelgono il pagamento e inviano. Include resi/sostituzioni
e onboarding nuovi clienti.

Consuma l'API di [`../portale-api`](../portale-api) (Bearer token Sanctum).

## Stack

- **Vue 3** (`<script setup>`, Composition API) + **Vue Router** (history mode)
- **Vite 6**, **Tailwind CSS v4** (`@tailwindcss/vite`)
- **axios** con interceptor (Bearer da `localStorage`, 401 → login)
- Stato in composabili reattivi (`src/stores/`), nessun Pinia — persistenza in
  `localStorage` con le stesse chiavi dello stack di riferimento
  (`token`, `user`, `selectedCustomerId`, `cart_items`, `catalog_*`, `dash_*`)

## Setup

```bash
npm install
cp .env.example .env
npm run dev            # http://localhost:5173  (proxy /api -> http://127.0.0.1:8000)
```

Assicurati che `portale-api` sia in ascolto (`php artisan serve`).
Login di test (seed): `agente@example.com` / `password`.

Build di produzione:

```bash
npm run build          # -> dist/
```

In produzione servi `dist/` da Caddy/Nginx e imposta `VITE_API_BASE_URL` sul
dominio dell'API (es. `https://api.<brand>/api`).

## Struttura

```
src/
  main.js · App.vue · style.css
  lib/        api.js (axios) · format.js (money/date)
  router/     8 rotte + guardia auth
  stores/     auth · customer · cart · config
  components/ AppNav · ProductCard · CustomerSelect · SizeColorMatrix · Spinner · EmptyState
  views/      Login · RegisterB2B · Dashboard · Catalog · ProductDetail · Cart · AgentNewClient · Sostituzioni
```

## Rotte

| Path | Vista |
|---|---|
| `/login` · `/register-b2b` | pubbliche |
| `/dashboard` | KPI agente, ordini, filtro stagione |
| `/catalog` | catalogo per cliente/stagione/categoria, ricerca, paginazione |
| `/product/:id` | dettaglio + matrice taglie/colori |
| `/cart` | riepilogo, IVA, spedizione, pagamento, conferma (+ redirect Stripe) |
| `/agent/new-client` | proposta nuovo cliente |
| `/sostituzioni` | richieste di cambio taglia/colore |

## Note

- Il **carrello è vincolato al cliente selezionato**: cambiando cliente si svuota.
- Il calcolo **IVA 22% / spese spedizione / importo minimo** usa i valori di
  `GET /api/app-config`.
- I video di sfondo della dashboard vanno in `public/videos/` (asset del brand,
  non versionati).
