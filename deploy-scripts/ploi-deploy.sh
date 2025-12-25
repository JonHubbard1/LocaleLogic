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
git pull origin main

##############################################################################
# 3. COMPOSER DEPENDENCIES
##############################################################################
echo "🎼 Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

##############################################################################
# 4. NPM DEPENDENCIES & BUILD
##############################################################################
echo "📦 Installing NPM dependencies..."
npm ci --production=false

echo "🏗️  Building frontend assets..."
npm run build

##############################################################################
# 5. STORAGE & CACHE DIRECTORIES
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
# 6. PERMISSIONS
##############################################################################
echo "🔐 Setting correct permissions..."
chown -R ploi:ploi storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

##############################################################################
# 7. ENVIRONMENT & CACHE
##############################################################################
echo "🔧 Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

##############################################################################
# 8. MIGRATIONS
##############################################################################
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction

##############################################################################
# 9. OPTIMIZE
##############################################################################
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Don't cache events in production if using queue workers
# php artisan event:cache

##############################################################################
# 10. STORAGE LINK
##############################################################################
echo "🔗 Creating storage link..."
php artisan storage:link || true

##############################################################################
# 11. RESTART SERVICES
##############################################################################
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# If using Octane (not currently), uncomment:
# php artisan octane:reload

##############################################################################
# 12. MAINTENANCE MODE OFF
##############################################################################
echo "✅ Disabling maintenance mode..."
php artisan up

##############################################################################
# 13. COMPLETION
##############################################################################
echo ""
echo "✨ Deployment complete!"
echo ""
echo "📊 Application Status:"
php artisan about --only=environment

echo ""
echo "🎉 LocaleLogic is now live!"
