#!/usr/bin/env bash
# Setup ambiente di sviluppo locale per portale-api (Windows + PHP winget + SQLite).
# Idempotente: si puo' rilanciare.
set -euo pipefail

PHPDIR="/c/Users/Utente/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe"
export PATH="$PHPDIR:$PATH"
COMPOSER="php /c/Users/Utente/bin/composer --no-interaction --no-security-blocking"
API="/c/Users/Utente/progetti/cdmsrl/portale-api"

cd "$API"

echo "==> [1/8] Skeleton Laravel 11 (file mancanti)"
if [ ! -f artisan ]; then
  SKEL_TMP="$(mktemp -d)"
  # ci interessano solo i FILE dello skeleton (artisan, public/, bootstrap/, config/, storage/):
  # il fallimento nella risoluzione delle dipendenze e' tollerato.
  $COMPOSER create-project laravel/laravel "$SKEL_TMP/skel" "^11.0" --prefer-dist --no-scripts --no-install || true
  if [ ! -f "$SKEL_TMP/skel/artisan" ]; then
    echo "!! skeleton non creato"; exit 1
  fi
  rm -f "$SKEL_TMP/skel/composer.json" "$SKEL_TMP/skel/composer.lock" "$SKEL_TMP/skel/.env.example" \
        "$SKEL_TMP/skel/.gitignore" "$SKEL_TMP/skel/README.md"
  rm -rf "$SKEL_TMP/skel/vendor"
  # copia ricorsiva senza sovrascrivere i file gia' presenti nel repo
  cp -rn "$SKEL_TMP/skel/." ./
  rm -rf "$SKEL_TMP"
fi

echo "==> [2/8] .env (SQLite)"
if [ ! -f .env ]; then
  cp .env.example .env
  # override per ambiente locale
  sed -i 's/^APP_ENV=.*/APP_ENV=local/' .env
  sed -i 's/^APP_URL=.*/APP_URL=http:\/\/localhost:8000/' .env
  sed -i 's/^SPA_URL=.*/SPA_URL=http:\/\/localhost:5173/' .env
  sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
  sed -i 's/^DB_DATABASE=.*/DB_DATABASE=database\/database.sqlite/' .env
  sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env
  sed -i 's/^SESSION_DOMAIN=.*/SESSION_DOMAIN=null/' .env
  sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env
  sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env
  sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' .env
fi
mkdir -p database
[ -f database/database.sqlite ] || : > database/database.sqlite

echo "==> [3/8] Dipendenze Composer"
$COMPOSER install --prefer-dist

echo "==> [4/8] APP_KEY"
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "==> [5/8] Config pacchetti"
php artisan vendor:publish --tag=settings-config --force >/dev/null 2>&1 || true
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations" --force >/dev/null 2>&1 || true

echo "==> [6/8] Migrazioni + seed"
php artisan migrate:fresh --seed --force

echo "==> [7/8] Storage link + asset Filament"
php artisan storage:link || true
php artisan filament:assets

echo "==> [8/8] Ottimizzazioni"
php artisan optimize:clear

echo
echo "PRONTO. Avvia con:"
echo "  export PATH=\"$PHPDIR:\$PATH\"; cd $API; php artisan serve"
echo "Pannello:  http://localhost:8000/access   (admin@example.com / password)"
