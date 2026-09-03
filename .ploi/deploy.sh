#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT_DIR=$(pwd)

echo "==> Pulling latest code"
git pull origin main

echo "==> Installing PHP dependencies"
php8.4 /usr/local/bin/composer install --no-interaction --prefer-dist --optimize-autoloader

echo "==> Building frontend assets"
npm ci --no-audit --no-fund
npm run build

echo "==> Clearing caches"
php8.4 artisan cache:clear
php8.4 artisan config:clear
php8.4 artisan view:clear
php8.4 artisan route:clear

echo "==> Optimizing application"
php8.4 artisan optimize

echo "==> Running migrations"
php8.4 artisan migrate --force || true

echo "==> Checking for known security vulnerabilities"
php8.4 /usr/local/bin/composer audit --no-interaction

echo "==> Reloading PHP-FPM"
echo "" | sudo -S service php8.4-fpm reload

DOMAIN=$(basename "$ROOT_DIR")
echo "==> Smoke testing https://$DOMAIN"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 30 "https://$DOMAIN" || echo "000")
if [[ "$HTTP_STATUS" != "200" ]]; then
    echo "ERROR: Smoke test failed with HTTP $HTTP_STATUS" >&2
    exit 1
fi

echo "🚀 Application deployed successfully!"
