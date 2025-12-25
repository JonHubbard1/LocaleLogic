# LocaleLogic Production Deployment Guide

This guide will help you deploy LocaleLogic to your production VPS with minimal downtime and efficient data transfer.

## Overview

We have two scripts to help with deployment:

1. **copy-data-to-production.sh** - Copies database and files from dev to production (run once during initial setup)
2. **ploi-deploy.sh** - Automated deployment script for Ploi (runs on every git push)

---

## Initial Production Setup

### 1. Prepare Production Server

SSH into your production server and ensure you have the required software:

```bash
# Check PostgreSQL is installed
psql --version

# Check PHP version
php -v  # Should be 8.3+

# Check Composer
composer --version

# Check Node.js
node -v  # Should be 18+
npm -v
```

### 2. Create Site in Ploi

1. Log into Ploi
2. Create a new site (e.g., `localelogic.uk`)
3. Choose PHP 8.3
4. Choose PostgreSQL as database
5. Enable Redis
6. Set web directory to `/public`

### 3. Clone Repository in Ploi

1. In Ploi, go to your site
2. Click "Repository" tab
3. Connect to GitHub repository: `JonHubbard1/LocaleLogic`
4. Set branch to `main`
5. Click "Install Repository"

### 4. Configure Environment Variables

In Ploi, go to "Environment" tab and set:

```env
APP_NAME="LocaleLogic"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://localelogic.uk

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ploi_localelogic
DB_USERNAME=ploi
DB_PASSWORD=your_database_password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

LOG_CHANNEL=stack
LOG_LEVEL=error
```

### 5. Set Up Ploi Deployment Script

1. In Ploi, go to "Deployment" tab
2. Paste the contents of `deploy-scripts/ploi-deploy.sh` into the deploy script field
3. **Important**: Update the path in the script:
   ```bash
   cd /home/ploi/localelogic.uk  # Change to your actual site path
   ```
4. Enable "Quick Deploy" if desired

### 6. Initial Deployment

Click "Deploy" in Ploi to run the first deployment. This will:
- Install dependencies
- Build frontend assets
- Run migrations (creating empty database structure)
- Set up permissions

---

## Copying Data from Development to Production

### Preparation

1. **On the production server**, download the data copy script:

```bash
cd /home/ploi/localelogic.uk
mkdir -p scripts
scp ploi@dev.localelogic.uk:/home/ploi/dev.localelogic.uk/deploy-scripts/copy-data-to-production.sh scripts/
chmod +x scripts/copy-data-to-production.sh
```

2. **Edit the script** with your actual paths and credentials:

```bash
nano scripts/copy-data-to-production.sh
```

Update these variables:
- `DEV_SERVER` - SSH connection string to dev server
- `DEV_PATH` - Path to dev installation
- `PROD_PATH` - Path to production installation
- `PROD_DB_NAME` - Production database name
- `PROD_DB_USER` - Production database user

### Set Up SSH Key Authentication (Recommended)

To avoid password prompts during data transfer:

```bash
# On production server
ssh-keygen -t ed25519 -C "production-to-dev"
ssh-copy-id ploi@dev.localelogic.uk

# Test connection
ssh ploi@dev.localelogic.uk "echo Connection successful"
```

### PostgreSQL Password Setup

To avoid password prompts for PostgreSQL:

```bash
# On production server
echo "localhost:5432:ploi_localelogic:ploi:YOUR_PASSWORD" >> ~/.pgpass
chmod 600 ~/.pgpass
```

### Run Data Copy

```bash
cd /home/ploi/localelogic.uk/scripts
./copy-data-to-production.sh
```

This will:
1. Dump the database from development (~5-10 GB)
2. Copy database dump to production
3. Restore database on production
4. Copy ONSUD files and boundary cache
5. Set correct permissions
6. Verify data integrity

**Time estimate**: 30-60 minutes depending on your connection speed

---

## Post-Migration Steps

### 1. Update Application Cache

```bash
cd /home/ploi/localelogic.uk
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Create Admin User

```bash
php artisan tinker
```

Then in Tinker:
```php
$user = new App\Models\User();
$user->name = 'Your Name';
$user->email = 'admin@localelogic.uk';
$user->password = bcrypt('secure-password-here');
$user->save();
exit
```

### 3. Create API Token

```bash
php artisan api:create-token admin@localelogic.uk --name="CaseMate Production"
```

Save the token securely!

### 4. Set Up Queue Worker (in Ploi)

1. Go to Ploi > Your Site > "Queue"
2. Add new queue worker:
   - Connection: `redis`
   - Queue: `default`
   - Processes: `3`
   - Sleep: `3`
3. Enable "Auto-restart on failure"

### 5. Set Up Scheduler (in Ploi)

1. Go to Ploi > Your Site > "Scheduler"
2. Ensure the Laravel scheduler is enabled:
   ```
   * * * * * cd /home/ploi/localelogic.uk && php artisan schedule:run >> /dev/null 2>&1
   ```

---

## Ongoing Deployments

After initial setup, deployments are automatic:

1. Push code to GitHub `main` branch
2. Ploi detects the push
3. Deployment script runs automatically
4. Application updates with zero downtime (maintenance mode)

### Manual Deployment

In Ploi dashboard, click the "Deploy" button to manually trigger deployment.

### Monitor Deployment

In Ploi:
- "Deployments" tab shows deployment history
- "Logs" tab shows deployment output
- Click on a deployment to see full logs

---

## SSL Certificate

1. In Ploi, go to "SSL" tab
2. Choose "Let's Encrypt"
3. Click "Install Certificate"
4. Enable "Force HTTPS redirect"

---

## Database Backups

### Set Up Automated Backups in Ploi

1. Go to Server > Backups
2. Enable daily PostgreSQL backups
3. Set retention period (e.g., 7 days)
4. Configure backup storage (S3, DO Spaces, etc.)

### Manual Backup

```bash
pg_dump -h localhost -U ploi -d ploi_localelogic \
    --format=custom \
    --compress=9 \
    --file=/home/ploi/backups/localelogic_$(date +%Y%m%d).dump
```

---

## Monitoring & Logs

### Application Logs

```bash
tail -f /home/ploi/localelogic.uk/storage/logs/laravel.log
```

### ONSUD Import Logs

```bash
ls -lh /home/ploi/localelogic.uk/storage/logs/onsud-import-*.log
tail -f /home/ploi/localelogic.uk/storage/logs/onsud-import-*.log
```

### Queue Worker Logs

In Ploi > Queue > Click on worker > View logs

### Database Size

```bash
psql -U ploi -d ploi_localelogic -c "
SELECT
    pg_size_pretty(pg_database_size('ploi_localelogic')) as database_size,
    (SELECT count(*) FROM properties) as property_count,
    (SELECT count(*) FROM boundary_caches) as cached_boundaries;
"
```

---

## Performance Optimization

### Enable OPcache (in Ploi)

1. Server > PHP > Choose PHP 8.3
2. Ensure OPcache is enabled
3. Set recommended values:
   ```
   opcache.memory_consumption=256
   opcache.interned_strings_buffer=16
   opcache.max_accelerated_files=20000
   opcache.validate_timestamps=0  # In production
   ```

### Redis Optimization

```bash
# Check Redis memory usage
redis-cli INFO memory

# Set max memory policy
redis-cli CONFIG SET maxmemory-policy allkeys-lru
```

### PostgreSQL Tuning

Edit PostgreSQL config for production workload:

```bash
sudo nano /etc/postgresql/15/main/postgresql.conf
```

Recommended settings for 8GB RAM server:
```
shared_buffers = 2GB
effective_cache_size = 6GB
maintenance_work_mem = 512MB
work_mem = 32MB
max_connections = 200
```

Restart PostgreSQL:
```bash
sudo systemctl restart postgresql
```

---

## Troubleshooting

### Deployment Failed

```bash
# Check deployment logs in Ploi
# SSH to server and check:
cd /home/ploi/localelogic.uk
git status
composer install
npm install
php artisan config:clear
```

### Database Connection Issues

```bash
# Test database connection
php artisan tinker
DB::connection()->getPdo();
```

### Permission Issues

```bash
cd /home/ploi/localelogic.uk
sudo chown -R ploi:ploi storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Queue Not Processing

```bash
# Restart queue workers in Ploi
# Or manually:
php artisan queue:restart
```

---

## Security Checklist

- [ ] APP_DEBUG=false in production
- [ ] Strong database password set
- [ ] SSL certificate installed
- [ ] Force HTTPS enabled
- [ ] Firewall configured (only ports 22, 80, 443)
- [ ] SSH key authentication enabled
- [ ] Regular backups configured
- [ ] API tokens secured
- [ ] Redis password set (if exposed)
- [ ] Server software up to date

---

## Support

For issues or questions:
- Check Laravel logs: `storage/logs/laravel.log`
- Review deployment logs in Ploi
- Check database connectivity: `php artisan tinker`
- Monitor queue workers in Ploi dashboard
