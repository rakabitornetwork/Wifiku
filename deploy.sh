#!/usr/bin/env bash
#
# Deploy Wifi Teslatech di VPS (tanpa Node/npm).
# Aset frontend (public/build) sudah ikut di-commit, jadi server TIDAK menjalankan `npm run build`.
#
# Pemakaian:
#   ./deploy.sh              # deploy/pembaruan biasa
#   ./deploy.sh --first-run  # pemasangan pertama: buat .env, APP_KEY, seed akun Owner
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
FIRST_RUN=0

for arg in "$@"; do
    case "$arg" in
        --first-run) FIRST_RUN=1 ;;
        *) echo "Argumen tidak dikenal: $arg" >&2; exit 1 ;;
    esac
done

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }

if [ ! -f public/build/manifest.json ]; then
    echo "public/build/manifest.json tidak ada. Jalankan 'npm run build' di komputer lokal, commit public/build, lalu push." >&2
    exit 1
fi

if [ "$FIRST_RUN" -eq 1 ] && [ ! -f .env ]; then
    step "Membuat .env dari .env.example"
    cp .env.example .env
    sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env
    echo "Edit .env (APP_URL, DB_*, TRUSTED_PROXIES) lalu jalankan ulang: ./deploy.sh --first-run"
    exit 0
fi

[ -f .env ] || { echo ".env belum ada. Jalankan: ./deploy.sh --first-run" >&2; exit 1; }

step "Mode maintenance ON"
"$PHP_BIN" artisan down --retry=30 || true
trap '"$PHP_BIN" artisan up || true' EXIT

if [ -d .git ]; then
    step "Menarik kode terbaru ($BRANCH)"
    git fetch --quiet origin
    git merge --ff-only "origin/$BRANCH"
fi

step "Memasang dependensi PHP"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ "$FIRST_RUN" -eq 1 ]; then
    if ! grep -qE '^APP_KEY=.+' .env; then
        step "Membuat APP_KEY"
        "$PHP_BIN" artisan key:generate --force
    fi
    if grep -qE '^DB_CONNECTION=sqlite' .env || ! grep -q '^DB_CONNECTION=' .env; then
        DB_FILE="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- || true)"
        DB_FILE="${DB_FILE:-database/database.sqlite}"
        [ -f "$DB_FILE" ] || { mkdir -p "$(dirname "$DB_FILE")"; touch "$DB_FILE"; }
    fi
fi

step "Migrasi database"
"$PHP_BIN" artisan migrate --force

if [ "$FIRST_RUN" -eq 1 ]; then
    step "Seeder (role, akun Owner, pengaturan awal)"
    "$PHP_BIN" artisan db:seed --force
    "$PHP_BIN" artisan storage:link || true
fi

step "Cache konfigurasi, route, dan view"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

step "Memuat ulang queue worker"
"$PHP_BIN" artisan queue:restart

step "Izin folder"
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

step "Selesai"
git log -1 --format='Versi terpasang: %h %s' 2>/dev/null || true
