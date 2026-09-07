# portale-api — Backend (Laravel 11 + Filament 3 + Sanctum)

Replica 1:1 dello stack di `valentinario.peels.it`: API REST per la SPA agenti +
pannello admin del brand.

## Stack
- **Laravel 11**, PHP 8.2+
- **Filament 3** — pannello admin su path `/access` (`config/portale.php → admin_path`)
- **Laravel Sanctum** — auth API con personal access token (header `Authorization: Bearer`)
- **spatie/laravel-settings** — credenziali integrazioni (cifrate)
- **barryvdh/laravel-dompdf** — PDF conferma d'ordine
- **stripe/stripe-php** — pagamenti carta B2B (Checkout)
- UUID v7 su tutte le entità di dominio

## Setup

> Questo pacchetto contiene **solo i file applicativi** (dominio, API, pannello).
> Va sovrapposto a uno skeleton Laravel 11 fresco per avere `artisan`, `public/`,
> i `config/*` standard, ecc.

```bash
# 1. skeleton Laravel 11 in una cartella temporanea
composer create-project laravel/laravel _skel "^11.0"

# 2. copia lo skeleton QUI senza sovrascrivere i file di questo repo
rsync -a --ignore-existing _skel/ ./ && rm -rf _skel

# 3. dipendenze extra
composer require filament/filament:"^3.2" laravel/sanctum:"^4.0" \
  barryvdh/laravel-dompdf:"^3.0" spatie/laravel-settings:"^3.4" stripe/stripe-php:"^16.2"
php artisan vendor:publish --tag=filament-config
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag=settings-config

# 4. ambiente
cp .env.example .env
php artisan key:generate

# DB (MySQL/MariaDB): imposta DB_* in .env, poi:
php artisan migrate
php artisan db:seed
php artisan storage:link

php artisan serve   # http://localhost:8000
```

Credenziali seed:
- Pannello admin → `http://localhost:8000/access` · `admin@example.com` / `password`
- API SPA (login) → `agente@example.com` / `password`

## Struttura

```
app/
  Enums/                 OrderStatus, PaymentMethod, ListinoTipo, ClienteTipo,
                         RichiestaStato, ProdottoTipo
  Models/                Agente, Cliente, Prodotto, VarianteProdotto, OrdineB2B,
                         OrdineRiga, Sostituzione(+Riga), Stagione, Categoria,
                         Sopracategoria, Genere, Colore, Taglia, AppConfig, User
  Http/Controllers/Api/  Auth, RegisterB2B, AppConfig, Filter, Product, Agent,
                         Order, Return
  Http/Resources/        ProductResource, ProductDetailResource
  Http/Middleware/       EnsureAgenteAttivo
  Services/              PriceService, StripeService
  Settings/              IntegrationSettings
  Providers/Filament/    AccessPanelProvider
database/migrations/     schema completo di dominio
database/settings/       migration settings integrazioni
routes/api.php           contratto API consumato dalla SPA
resources/views/pdf/     template PDF ordine
```

## Contratto API (SPA agenti)

| Metodo | Endpoint | Note |
|---|---|---|
| POST | `/api/login` | → `{ token, user }` |
| POST | `/api/logout` | revoca il token corrente |
| POST | `/api/forgot-password` · `/api/reset-password` | broker `agenti` |
| POST | `/api/register-b2b` | multipart, upload documenti |
| GET  | `/api/filters` | pubblico — categorie/stagioni/pacchetti/tessuti |
| GET  | `/api/app-config` | vat, spese_spedizione, importo_minimo_ordine, … |
| GET  | `/api/products` · `/api/products/{id}` | prezzi per listino cliente (`?customer_id=`) |
| GET  | `/api/agent/customers` · `/agent/orders` · `/agent/seasons` · `/agent/returns` | |
| POST | `/api/clients` | proposta nuovo cliente (stato "in_attesa") |
| POST | `/api/orders` | crea ordine, scala giacenze, calcola totali |
| GET/PUT/DELETE | `/api/orders/{id}` | |
| GET  | `/api/orders/{id}/pdf/v2` | blob PDF |
| POST | `/api/orders/{id}/checkout-session` | Stripe Checkout |
| POST | `/api/orders/{id}/verify-stripe-payment` | `{ session_id }` |
| POST | `/api/returns` | richiesta sostituzione |
| POST | `/api/stripe/webhook` | `checkout.session.completed` |

## Regole di business (`App\Services\PriceService`)
- Prezzo base = `variante.prezzo` (listino **standard**). Listino cliente/agente
  applica un moltiplicatore (`ListinoTipo::multiplier()`; `plus5` = ×1.05).
- IVA (default 22%) calcolata su `subtotale + spese_spedizione`.
- `spese_spedizione` e `importo_minimo_ordine` da `app_config` (pannello).
- Carrello vincolato al cliente selezionato (enforce lato SPA + validazione ordine).

## TODO — prossimi pezzi
1. **Filament Resources** (Prodotto con repeater varianti, OrdineB2B con azioni
   Cambia Stato / PDF / Invia Mail / Invia a ERP, Cliente, Agente, tassonomie),
   pagina `ConfigurazioniSistema`, widget dashboard (StatsOverview + 3 chart).
2. **Integrazioni**: `ExternalChannel` + `ShopifyChannel` / `WooCommerceChannel` /
   `ErpChannel`, job in coda, comandi `sync:giacenze` e `sync:clienti`.
3. **Mail**: `OrdineConfermatoMail` + template.
4. **Frontend** `portale-web/` (Vue 3 + Vite + Tailwind v4).
