# CDM SRL — Portale Agenti B2B

Portale di raccolta ordini per agenti/rappresentanti B2B (settore moda), con
pannello di gestione per il brand e sincronizzazione verso e-commerce ed ERP.

Replica 1:1 dello stack di riferimento `valentinario.peels.it`.

## Monorepo

| Cartella | Descrizione | Stack |
|---|---|---|
| [`portale-api/`](portale-api/) | API REST + pannello admin | Laravel 11 · Filament 3 · Sanctum · MySQL |
| [`portale-web/`](portale-web/) | SPA agenti | Vue 3 · Vite · Tailwind CSS v4 · axios |
| [`infra/`](infra/) | Deploy di produzione | Caddy · Docker Compose · MariaDB · Redis |
| [`docs/`](docs/) | Integrazioni | WinMino (ERP) · Shopify (B2C) |

## Architettura

```
   WinMino  ──import prodotti/prezzi/giacenze/clienti──▶  portale  ──push──▶  Shopify (B2C)
   (ERP,                                                   (B2B, gli agenti)
    master)                                                     │
                                                                ▼  nuovo ordine
                                            mail al backoffice CDM → caricamento manuale su WinMino

  brand.example.it            api.brand.example.it
  ┌───────────────┐           ┌──────────────────────────┐
  │  portale-web  │  ── API ──▶  portale-api (Laravel)    │
  │  (Vue SPA)    │  Bearer   │  ├─ /api/*  REST          │
  └───────────────┘  token    │  └─ /access Filament panel│
                              └───────┬──────────────────┘
                                      │ job in coda
                        ┌─────────────┼─────────────┐
                        ▼             ▼             ▼
                     Stripe        Shopify        WinMino
                   (pagamenti)   (push B2C)     (import GET)
```

## Stato

- [x] **Backend — fondamenta**: schema DB, model, enum, API SPA (auth Sanctum,
      catalogo, ordini, PDF, Stripe, sostituzioni), pannello Filament base, seeder
- [x] **Backend — pannello Filament**: risorse (anagrafiche, attributi, Prodotto
      read-first, Ordini B2B, Sostituzioni), Configurazioni Sistema, Dashboard
      con widget, upload massivo immagini
- [x] **Integrazioni**: import WinMino (`sync:prodotti` / `sync:clienti`, GET via
      decoder `meta`), push Shopify (`ChannelManager` / `ShopifyChannel`), notifica
      ordine al backoffice CDM, job + comandi schedulati
- [x] **Frontend `portale-web/`**: 8 view, carrello vincolato al cliente,
      redirect/verify Stripe
- [x] **Infra**: `docker compose` (Caddy + api + worker + scheduler + MariaDB +
      Redis), Dockerfile multi-stage, checklist go-live
- [ ] Attivazione WinMino (endpoint/credenziali da Magis — vedi
      [`docs/winmino-integration.md`](docs/winmino-integration.md))
- [ ] Test dei canali e-commerce contro store reali

> **Prodotto**: la fonte è WinMino (ERP). Nel pannello codice/testi/varianti/
> prezzi/giacenze sono sola lettura; si gestiscono solo immagini e flag di
> pubblicazione.

Setup sviluppo: [`portale-api/README.md`](portale-api/README.md) ·
[`portale-web/README.md`](portale-web/README.md).
Deploy: [`infra/README.md`](infra/README.md).
