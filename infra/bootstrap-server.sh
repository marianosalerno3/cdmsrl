#!/usr/bin/env bash
# ----------------------------------------------------------------------
#  Installazione del portale B2B CDM su un server Ubuntu 24.04 nuovo.
#
#  Cosa fa (e si puo' rilanciare senza danni):
#    1. aggiorna il sistema, attiva aggiornamenti di sicurezza, fail2ban e swap
#    2. firewall: aperte solo SSH (22), HTTP (80) e HTTPS (443)
#    3. installa Docker
#    4. scarica il codice in /opt/cdmsrl
#    5. crea infra/.env con password casuali (mai stampate a video)
#    6. avvia il portale (database, code, scheduler, web) con certificati HTTPS automatici
#    7. crea l'utente amministratore del pannello e ne salva la password in un file protetto
#
#  Uso:   sudo bash bootstrap-server.sh
#  (le domande mancanti vengono chieste a video; oppure si passano come variabili:
#   APP_DOMAIN, API_DOMAIN, ACME_EMAIL, BACKOFFICE_EMAIL, ADMIN_EMAIL)
# ----------------------------------------------------------------------
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/marianosalerno3/cdmsrl.git}"
BRANCH="${BRANCH:-build/portale-b2b}"
INSTALL_DIR="${INSTALL_DIR:-/opt/cdmsrl}"
CRED_FILE="/root/cdmsrl-credenziali.txt"

say()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()   { printf '    \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '    \033[33m! %s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31mERRORE: %s\033[0m\n' "$*" >&2; exit 1; }

ask() { # ask VAR "domanda" [default]
  local var="$1" prompt="$2" def="${3:-}" val="${!1:-}"
  if [ -z "$val" ]; then
    if [ -r /dev/tty ]; then
      read -r -p "$prompt${def:+ [$def]}: " val < /dev/tty || true
    fi
    val="${val:-$def}"
  fi
  [ -n "$val" ] || die "Manca il valore di $var"
  printf -v "$var" '%s' "$val"
}

rand() { # stringa casuale alfanumerica (senza head: con pipefail darebbe errore SIGPIPE)
  local s; s="$(openssl rand -base64 64 | tr -dc 'A-Za-z0-9')"; printf '%s' "${s:0:${1:-24}}"
}

# ------------------------------ controlli ------------------------------
[ "$(id -u)" -eq 0 ] || die "Lancia lo script come root:  sudo bash bootstrap-server.sh"
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || die "Serve Ubuntu (trovato: ${PRETTY_NAME:-sconosciuto})"
ok "Sistema: ${PRETTY_NAME}"

APP_DOMAIN="${APP_DOMAIN:-agenti.cdmsrlb2b.it}"
API_DOMAIN="${API_DOMAIN:-api.cdmsrlb2b.it}"
ask ACME_EMAIL       "Email per i certificati HTTPS (avvisi di scadenza)"
ask BACKOFFICE_EMAIL "Email che riceve gli ordini degli agenti (piu' indirizzi separati da virgola)"
ask ADMIN_EMAIL      "Email di accesso al pannello amministratore" "$ACME_EMAIL"

export DEBIAN_FRONTEND=noninteractive

# ----------------------------- pacchetti base ---------------------------
say "Aggiornamento del sistema e strumenti di base"
apt-get update -y
apt-get upgrade -y
apt-get install -y ca-certificates curl git ufw fail2ban unattended-upgrades openssl dnsutils
ok "Pacchetti installati"

# ------------------ il DNS punta davvero a questo server? ---------------
say "Controllo dei domini"
SERVER_IP="$(curl -4fsS https://api.ipify.org || true)"
[ -n "$SERVER_IP" ] || warn "Non riesco a leggere l'IP pubblico del server"
DNS_OK=1
for d in "$APP_DOMAIN" "$API_DOMAIN"; do
  R="$(dig +short A "$d" @1.1.1.1 | tail -n1)"
  if [ -n "$SERVER_IP" ] && [ "$R" = "$SERVER_IP" ]; then
    ok "$d -> $R"
  else
    warn "$d punta a '${R:-nessun record}' ma il server e' $SERVER_IP"
    DNS_OK=0
  fi
done
if [ "$DNS_OK" -ne 1 ] && [ "${FORCE:-0}" != "1" ]; then
  die "I record DNS (tipo A) non puntano ancora a questo server. Correggili su GoDaddy, aspetta qualche minuto e rilancia. (Per procedere comunque: FORCE=1)"
fi

# ------------------------- sicurezza e risorse --------------------------
say "Firewall, protezione accessi e aggiornamenti automatici"
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp  >/dev/null
ufw allow 443/tcp >/dev/null
ufw --force enable >/dev/null
ok "Firewall attivo: aperte solo 22, 80, 443"
systemctl enable --now fail2ban >/dev/null 2>&1 || true
ok "fail2ban attivo (blocca i tentativi ripetuti di accesso)"
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true
ok "Aggiornamenti di sicurezza automatici attivi"

if ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  ok "Swap da 2 GB creato"
fi

# -------------------------------- Docker --------------------------------
say "Docker"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker >/dev/null 2>&1 || true
docker compose version >/dev/null 2>&1 || die "Docker Compose non disponibile"
ok "$(docker --version)"

# --------------------------------- codice --------------------------------
say "Download del portale ($BRANCH)"
if [ -d "$INSTALL_DIR/.git" ]; then
  git -C "$INSTALL_DIR" fetch origin "$BRANCH"
  git -C "$INSTALL_DIR" checkout "$BRANCH"
  git -C "$INSTALL_DIR" reset --hard "origin/$BRANCH"
else
  git clone --branch "$BRANCH" "$REPO_URL" "$INSTALL_DIR"
fi
ok "Codice in $INSTALL_DIR ($(git -C "$INSTALL_DIR" rev-parse --short HEAD))"

# ---------------------------------- .env ---------------------------------
cd "$INSTALL_DIR/infra"
if [ -f .env ]; then
  ok ".env gia' presente: non viene toccato"
else
  say "Creazione della configurazione (.env) con password casuali"
  cp .env.example .env
  setv() { # setv CHIAVE VALORE  (sostituisce la riga CHIAVE=...)
    local k="$1" v="$2"
    v="${v//\\/\\\\}"; v="${v//&/\\&}"; v="${v//|/\\|}"
    sed -i "s|^${k}=.*|${k}=${v}|" .env
  }
  setv APP_DOMAIN        "$APP_DOMAIN"
  setv API_DOMAIN        "$API_DOMAIN"
  setv ACME_EMAIL        "$ACME_EMAIL"
  setv BACKOFFICE_EMAIL  "$BACKOFFICE_EMAIL"
  setv MAIL_FROM_ADDRESS "ordini@${APP_DOMAIN#*.}"
  setv APP_KEY           "base64:$(openssl rand -base64 32)"
  setv DB_PASSWORD       "$(rand 28)"
  setv DB_ROOT_PASSWORD  "$(rand 28)"
  chmod 600 .env
  ok "Configurazione creata (permessi riservati)"
fi

# --------------------------------- avvio ---------------------------------
say "Costruzione e avvio del portale (la prima volta richiede 5-10 minuti)"
docker compose up -d --build

say "Attendo che il portale sia pronto"
READY=0
for i in $(seq 1 90); do
  if docker compose exec -T api curl -fsS http://127.0.0.1:8080/up >/dev/null 2>&1; then READY=1; break; fi
  sleep 5
done
if [ "$READY" -ne 1 ]; then
  docker compose ps || true
  docker compose logs --tail=60 api || true
  die "Il portale non risponde dopo 7 minuti: copia l'output qui sopra e mandalo per il controllo."
fi
ok "Applicazione avviata (migrazioni del database eseguite)"

# ---------------------------------- admin --------------------------------
say "Utente amministratore"
if [ -f "$CRED_FILE" ] && grep -q "^ADMIN_EMAIL=$ADMIN_EMAIL$" "$CRED_FILE"; then
  ok "Utente gia' creato: password in $CRED_FILE"
else
  ADMIN_PASS="$(rand 20)"
  docker compose exec -T api php artisan tinker --execute='\App\Models\User::updateOrCreate(["email"=>"'"$ADMIN_EMAIL"'"],["name"=>"Amministratore","password"=>"'"$ADMIN_PASS"'","is_active"=>true]);' >/dev/null
  umask 077
  {
    echo "# Credenziali pannello portale B2B CDM - conservare e poi cambiare la password"
    echo "ADMIN_EMAIL=$ADMIN_EMAIL"
    echo "ADMIN_PASSWORD=$ADMIN_PASS"
  } > "$CRED_FILE"
  ok "Utente creato. Password salvata in $CRED_FILE (visibile solo a root)"
fi

# --------------------------------- esito ---------------------------------
say "Verifica finale"
for d in "$API_DOMAIN" "$APP_DOMAIN"; do
  CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "https://$d/" || true)"
  printf '    https://%s  ->  HTTP %s\n' "$d" "$CODE"
done

cat <<EOF

================================================================
 INSTALLAZIONE COMPLETATA

  Portale agenti :  https://$APP_DOMAIN
  Pannello admin :  https://$API_DOMAIN/access
  Email admin    :  $ADMIN_EMAIL
  Password admin :  cat $CRED_FILE

 Se i siti non si aprono subito, aspetta 1-2 minuti (i certificati HTTPS
 si generano al primo accesso) e riprova.

 Ancora da configurare (Configurazioni Sistema nel pannello / .env):
  - SMTP per le email:  modifica MAIL_HOST/MAIL_USERNAME/MAIL_PASSWORD in
    $INSTALL_DIR/infra/.env  poi:  cd $INSTALL_DIR/infra && docker compose up -d
  - Stripe (chiavi live + webhook), WinMino, Shopify
================================================================
EOF
