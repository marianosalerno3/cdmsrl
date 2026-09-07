# CDM SRL — Portale Agenti B2B

Portale di raccolta ordini per agenti/rappresentanti B2B (settore moda), con
pannello di gestione per il brand e sincronizzazione verso e-commerce ed ERP.

Replica 1:1 dello stack di riferimento `valentinario.peels.it`.

## Monorepo

| Cartella | Descrizione | Stack |
|---|---|---|
| [`portale-api/`](portale-api/) | API REST + pannello admin | Laravel 11 · Filament 3 · Sanctum · MySQL |
| `portale-web/` _(in arrivo)_ | SPA agenti | Vue 3 · Vite · Tailwind CSS v4 · axios |
| `infra/` _(in arrivo)_ | Reverse proxy, deploy | Caddy · docker-compose |

## Architettura

```
  brand.example.it            api.brand.example.it
  ┌───────────────┐           ┌──────────────────────────┐
  │  portale-web  │  ── API ──▶  portale-api (Laravel)    │
  │  (Vue SPA)    │  Bearer   │  ├─ /api/*  REST          │
  └───────────────┘  token    │  └─ /access Filament panel│
                              └───────┬──────────────────┘
                                      │ job in coda
                        ┌─────────────┼─────────────┬───────────────┐
                        ▼             ▼             ▼               ▼
                     Stripe        Shopify     WooCommerce      ERP (WinMino
                   (pagamenti)     (B2C)      (WordPress B2B)   o equivalente)
```

## Stato

- [x] **Backend — fondamenta**: schema DB, model, enum, API SPA (auth Sanctum,
      catalogo, ordini, PDF, Stripe, sostituzioni), pannello Filament base, seeder
- [ ] Backend — Filament Resources + pagina Configurazioni + widget dashboard
- [ ] Backend — integrazioni (Shopify / WooCommerce / ERP) + job + comandi sync
- [ ] Frontend `portale-web/`
- [ ] Infra / deploy

Dettagli e setup: [`portale-api/README.md`](portale-api/README.md).
