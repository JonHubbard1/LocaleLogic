#!/bin/bash

##############################################################################
# LocaleLogic Ploi Deployment Script
#
# This script should be added to your Ploi site's deployment script
# It will run on every git push to deploy your application
#
# In Ploi: Site > Deployment > Deploy Script
##############################################################################

set -e  # Exit on any error

echo "🚀 Starting LocaleLogic deployment..."

cd /home/ploi/localelogic.uk  # Update this path to match your Ploi site path

##############################################################################
# 1. MAINTENANCE MODE
##############################################################################
echo "📦 Enabling maintenance mode..."
php artisan down --retry=60 --render="errors::503" || true

##############################################################################
# 2. GIT PULL (Ploi does this automatically, but we'll be explicit)
##############################################################################
echo "📥 Pulling latest code..."

# Stash any local changes to prevent merge conflicts
if ! git diff-index --quiet HEAD --; then
    echo "⚠️  Local changes detected, stashing..."
    git stash push -u -m "Auto-stash before deployment $(date +%Y%m%d-%H%M%S)"
    STASHED=true
else
    STASHED=false
fi

# Pull latest code
git pull origin main

# Optionally restore stashed changes (usually we want to discard them in production)
# Uncomment the next line if you want to restore local changes after pull
# if [ "$STASHED" = true ]; then git stash pop; fi

##############################################################################
# 3. FLUX UI PRO AUTHENTICATION
##############################################################################
echo "🔑 Configuring Flux UI Pro authentication..."
# Add Flux Pro repository (if not already in composer.json)
composer config repositories.flux-pro composer https://composer.fluxui.dev --no-interaction

# Set authentication credentials for Flux UI Pro
composer config http-basic.composer.fluxui.dev jon@jonhubbard.org 7b668b2b-338d-4618-a9cb-a1eae76b2725 --no-interaction

##############################################################################
# 4. COMPOSER DEPENDENCIES
##############################################################################
echo "🎼 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

##############################################################################
# 5. NPM DEPENDENCIES & BUILD
##############################################################################
echo "📦 Installing NPM dependencies..."
npm ci --production=false

echo "🏗️  Building frontend assets..."
npm run build

##############################################################################
# 6. STORAGE & CACHE DIRECTORIES
##############################################################################
echo "📁 Ensuring storage directories exist..."
mkdir -p storage/app/onsud
mkdir -p storage/app/public
mkdir -p storage/app/livewire-tmp
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

##############################################################################
# 7. PERMISSIONS
##############################################################################
echo "🔐 Setting correct permissions..."
# Note: Ploi manages ownership automatically, we only set permissions
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
# If specific files need ownership changes, Ploi will handle it via its own processes

##############################################################################
# 8. ENVIRONMENT & CACHE
##############################################################################
echo "🔧 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

##############################################################################
# 9. MIGRATIONS
##############################################################################
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction

##############################################################################
# 10. OPTIMIZE
##############################################################################
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Don't cache events in production if using queue workers
# php artisan event:cache

##############################################################################
# 11. STORAGE LINK
##############################################################################
echo "🔗 Creating storage link..."
php artisan storage:link || true

##############################################################################
# 12. RESTART SERVICES
##############################################################################
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# If using Octane (not currently), uncomment:
# php artisan octane:reload

##############################################################################
# 13. MAINTENANCE MODE OFF
##############################################################################
echo "✅ Disabling maintenance mode..."
php artisan up

##############################################################################
# 14. COMPLETION
##############################################################################
echo ""
echo "✨ Deployment complete!"
echo ""
echo "📊 Application Status:"
php artisan about --only=environment

echo ""
echo "🎉 LocaleLogic is now live!"
