#!/bin/bash
set -e

BRANCH="${DEPLOY_BRANCH:-main}"

echo "Starting deployment on branch: $BRANCH"

cd "$(dirname "$0")"

echo "Pulling latest code..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "Installing NPM dependencies and building assets..."
npm install --no-audit --no-fund
./node_modules/.bin/vite build

echo "Clearing caches..."
php artisan optimize:clear

echo "Running database migrations..."
php artisan migrate --force

echo "Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Reloading PHP-FPM..."
if sudo systemctl reload php8.4-fpm 2>/dev/null; then
    echo "PHP-FPM reloaded successfully"
elif sudo service php8.4-fpm reload 2>/dev/null; then
    echo "PHP-FPM reloaded successfully"
else
    echo "Warning: Could not reload PHP-FPM"
fi

echo "Deployment complete."
