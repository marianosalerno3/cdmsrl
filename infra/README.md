# infra/ — deploy di produzione

Stack Docker: **Caddy** (edge TLS + reverse proxy) · **portale-api** (nginx+php-fpm)
· **worker code** · **scheduler** · **MariaDB** · **Redis**.

```
                 :443
        ┌────────────────────┐
        │       caddy        │  TLS automatico (Let's Encrypt)
        └─────────┬──────────┘
    cdmsrl.…      │      api.cdmsrl.…
  (SPA statica)   │   reverse_proxy → api:8080
                  ▼
        ┌────────────────────┐   ┌──────────┐   ┌────────┐
        │        api         │──▶│ mariadb  │   │ redis  │
        │  (portale-api)     │──▶│          │   │        │
        └────────────────────┘   └──────────┘   └────────┘
             ▲        ▲
        queue│  scheduler (stessa immagine)
```

## Prerequisiti

- Un server Linux con **Docker** + **Docker Compose v2**.
- DNS: `A`/`AAAA` di `APP_DOMAIN` e `API_DOMAIN` che puntano al server.
- Porte **80** e **443** aperte (Caddy fa il challenge ACME sulla 80).

## Primo deploy

```bash
cd infra
cp .env.example .env
#  → compila .env: domini, ACME_EMAIL, password DB, SMTP

# genera la APP_KEY e incollala in .env
docker compose run --rm api php artisan key:generate --show

docker compose up -d --build
```

Al primo avvio l'immagine `api` esegue in automatico (env `AUTORUN_*`):
`migrate --force`, `config/route/view cache`, `storage:link`.

Crea l'utente admin del pannello:

```bash
docker compose exec api php artisan tinker --execute="\
  \App\Models\User::updateOrCreate(['email'=>'admin@cdmsrl.it'],\
  ['name'=>'Amministratore','password'=>bcrypt('CAMBIALA'),'is_active'=>true]);"
```

Pannello: `https://<API_DOMAIN>/access` · SPA: `https://<APP_DOMAIN>`

## Aggiornamenti

```bash
git pull
cd infra
docker compose up -d --build          # rebuild immagini, migrazioni automatiche
docker compose exec api php artisan optimize
```

Rollback: `git checkout <tag-precedente> && docker compose up -d --build`.

## Operazioni comuni

| | |
|---|---|
| Log api | `docker compose logs -f api` |
| Log code | `docker compose logs -f queue` |
| Coda in tempo reale | `docker compose exec api php artisan queue:monitor` |
| Backup DB | `docker compose exec db mariadb-dump -u root -p$DB_ROOT_PASSWORD $DB_DATABASE > dump.sql` |
| Shell Laravel | `docker compose exec api php artisan tinker` |
| Ricalcolo cache | `docker compose exec api php artisan optimize:clear` |

## Volumi persistenti

`db-data` (database) · `api-storage` (upload immagini prodotto) · `api-logs`
· `caddy-data` (certificati TLS) · `redis-data`.
**Includere `db-data` e `api-storage` nel backup.**

---

## Checklist go-live

### Applicazione
- [ ] `.env` di produzione completo; `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generata
- [ ] Utente admin creato e password di default cambiata
- [ ] `php artisan migrate --force` andato a buon fine (log `api`)
- [ ] SPA raggiungibile su `APP_DOMAIN`, login agente OK
- [ ] Pannello raggiungibile su `API_DOMAIN/access`, login admin OK
- [ ] Upload immagine prodotto → visibile via `API_DOMAIN/storage/...`
- [ ] Invio ordine di prova end-to-end (carrello → conferma → PDF → email)

### Integrazioni (Configurazioni Sistema)
- [ ] **Stripe**: chiavi *live*, webhook `https://<API_DOMAIN>/api/stripe/webhook` registrato su Stripe, `stripe_webhook_secret` inserito
- [ ] **WinMino**: `erp_driver=winmino`, base URL raggiungibile dal server (VPN/whitelist IP), credenziali; pulsante **Test ERP** verde
- [ ] **Shopify**: shop domain + Admin API token; pulsante **Test Shopify** verde
- [ ] `sync:clienti` e `sync:giacenze` girano senza errori (log `scheduler`)
- [ ] Import iniziale prodotti/clienti da WinMino verificato su un campione

### Sicurezza / infra
- [ ] DNS CAA che consente Let's Encrypt; certificati emessi (`docker compose logs caddy`)
- [ ] Firewall: solo 22/80/443 pubbliche; DB e Redis non esposti
- [ ] Backup automatico di `db-data` + `api-storage` schedulato e **ripristino testato**
- [ ] `SANCTUM_STATEFUL_DOMAINS` e `config/cors.php` limitati ad `APP_DOMAIN`
- [ ] Rotazione log attiva (`storage/logs`), `LOG_LEVEL=warning` in produzione
- [ ] Monitoraggio uptime su `APP_DOMAIN` e `API_DOMAIN/up`

### Consegna al cliente
- [ ] Repo trasferito all'organizzazione GitHub del cliente (o accesso concesso)
- [ ] Credenziali (Stripe/Shopify/Woo/WinMino/DB/SMTP) consegnate via canale sicuro, **non** nel repo
- [ ] README di root + `portale-api/README.md` + questo file aggiornati
- [ ] Nota su chi gestisce dominio, DNS e rinnovo server
