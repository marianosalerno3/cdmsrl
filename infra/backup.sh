#!/usr/bin/env bash
# Backup del portale: database (dump SQL) + file caricati (immagini prodotto, documenti dei clienti).
# Si lancia dal server, da cron (vedi infra/README.md → "Backup automatico").
#
#   /opt/cdmsrl/infra/backup.sh
#
# Variabili opzionali (da infra/.env o dall'ambiente):
#   BACKUP_DIR         dove salvare (default /var/backups/portale)
#   BACKUP_KEEP_DAYS   per quanti giorni tenere i backup (default 14)
#   BACKUP_REMOTE      copia fuori dal server, es. "uXXXX@uXXXX.your-storagebox.de:portale/" (richiede ssh senza password)
#   BACKUP_PING_URL    indirizzo da chiamare a backup riuscito (es. healthchecks.io): se non arriva, ti avvisa lui

set -euo pipefail

cd "$(dirname "$0")"
# .env è fatto per docker compose, non per la shell (spazi, UID…): si leggono solo le variabili che servono
env_get() { [ -f .env ] && grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//' || true; }
BACKUP_DIR="${BACKUP_DIR:-$(env_get BACKUP_DIR)}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-$(env_get BACKUP_KEEP_DAYS)}"
BACKUP_REMOTE="${BACKUP_REMOTE:-$(env_get BACKUP_REMOTE)}"
BACKUP_PING_URL="${BACKUP_PING_URL:-$(env_get BACKUP_PING_URL)}"

DIR="${BACKUP_DIR:-/var/backups/portale}"
KEEP="${BACKUP_KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DIR"
chmod 700 "$DIR"

DB_FILE="$DIR/db-$STAMP.sql.gz"
FILES_FILE="$DIR/files-$STAMP.tar.gz"

# 1. database: copia coerente senza fermare il portale (le credenziali sono già nel container db)
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --events "$MARIADB_DATABASE"' \
  | gzip -9 > "$DB_FILE"

# 2. file caricati
docker compose exec -T api tar czf - -C /var/www/html/storage/app . > "$FILES_FILE"

# 3. controlli: un backup vuoto o rotto non deve passare per buono
for f in "$DB_FILE" "$FILES_FILE"; do
  gzip -t "$f"
done
if [ "$(stat -c %s "$DB_FILE")" -lt 2000 ]; then
  echo "ERRORE: il dump del database è troppo piccolo ($DB_FILE)" >&2
  exit 1
fi

# 4. copia fuori dal server (se configurata)
if [ -n "${BACKUP_REMOTE:-}" ]; then
  rsync -a -e "ssh -o BatchMode=yes" "$DB_FILE" "$FILES_FILE" "$BACKUP_REMOTE"
fi

# 5. pulizia dei backup vecchi
find "$DIR" -maxdepth 1 -type f \( -name 'db-*.sql.gz' -o -name 'files-*.tar.gz' \) -mtime +"$KEEP" -delete

echo "Backup ok: $DB_FILE ($(du -h "$DB_FILE" | cut -f1)), $FILES_FILE ($(du -h "$FILES_FILE" | cut -f1))"

# 6. segnala che è andato bene (solo se tutto il resto è riuscito)
if [ -n "${BACKUP_PING_URL:-}" ]; then
  curl -fsS -m 10 --retry 3 "$BACKUP_PING_URL" > /dev/null || echo "Avviso: ping di controllo non riuscito" >&2
fi
