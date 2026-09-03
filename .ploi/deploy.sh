#!/bin/bash
set -e

BRANCH="${DEPLOY_BRANCH:-main}"

echo "Starting deployment on branch: $BRANCH"

# Change to project root (script is at .ploi/deploy.sh)
cd "$(dirname "$0")/.."

echo "Pulling latest code..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "Installing Composer dependencies..."
php8.4 /usr/local/bin/composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "Installing NPM dependencies and building assets..."
npm install --no-audit --no-fund
./node_modules/.bin/vite build

echo "Clearing caches..."
php8.4 artisan optimize:clear

echo "Running database migrations..."
php8.4 artisan migrate --force

echo "Rebuilding caches..."
php8.4 artisan config:cache
php8.4 artisan route:cache
php8.4 artisan view:cache

echo "Reloading PHP-FPM..."
if sudo systemctl reload php8.4-fpm 2>/dev/null; then
    echo "PHP-FPM reloaded successfully"
elif sudo service php8.4-fpm reload 2>/dev/null; then
    echo "PHP-FPM reloaded successfully"
else
    echo "Warning: Could not reload PHP-FPM"
fi

echo "Deployment complete."
