# LocaleLogic Quick Deployment Reference

## Initial Production Setup (One-Time)

### 1. Set Up Production in Ploi
```bash
# Create new site in Ploi dashboard
# - Site name: localelogic.uk
# - PHP 8.3
# - PostgreSQL database
# - Redis enabled
```

### 2. Connect GitHub Repository
```bash
# In Ploi > Repository tab:
# - Repository: JonHubbard1/LocaleLogic
# - Branch: main
# - Click "Install Repository"
```

### 3. Set Environment Variables
```bash
# In Ploi > Environment tab, set production values
# See DEPLOYMENT_GUIDE.md for full .env configuration
```

### 4. Add Ploi Deploy Script
```bash
# Copy contents of deploy-scripts/ploi-deploy.sh
# Paste into Ploi > Deployment > Deploy Script
# Update the cd path to match your site
```

### 5. Run Initial Deployment
```bash
# In Ploi dashboard, click "Deploy"
# This creates the database structure
```

---

## Copy Data from Dev to Production (One-Time)

### On Production Server:

```bash
# 1. SSH to production
ssh ploi@localelogic.uk

# 2. Get the data copy script
cd /home/ploi/localelogic.uk
mkdir -p scripts
scp ploi@dev.localelogic.uk:/home/ploi/dev.localelogic.uk/deploy-scripts/copy-data-to-production.sh scripts/
chmod +x scripts/copy-data-to-production.sh

# 3. Edit configuration
nano scripts/copy-data-to-production.sh
# Update: DEV_SERVER, DEV_PATH, PROD_PATH, PROD_DB_NAME, PROD_DB_USER

# 4. Set up SSH keys (no password prompts)
ssh-keygen -t ed25519 -C "prod-to-dev"
ssh-copy-id ploi@dev.localelogic.uk

# 5. Set up PostgreSQL password (no password prompts)
echo "localhost:5432:ploi_localelogic:ploi:YOUR_PASSWORD" >> ~/.pgpass
chmod 600 ~/.pgpass

# 6. Run data copy (30-60 minutes)
./scripts/copy-data-to-production.sh

# 7. Verify
psql -U ploi -d ploi_localelogic -c "SELECT COUNT(*) FROM properties;"
```

---

## Ongoing Deployments (Automatic)

```bash
# On your local machine:
git add .
git commit -m "Your changes"
git push origin main

# Ploi automatically deploys!
```

---

## Common Production Tasks

### Create Admin User
```bash
ssh ploi@localelogic.uk
cd /home/ploi/localelogic.uk
php artisan tinker
```
```php
$user = new App\Models\User();
$user->name = 'Admin Name';
$user->email = 'admin@example.com';
$user->password = bcrypt('secure-password');
$user->save();
exit
```

### Create API Token
```bash
php artisan api:create-token admin@example.com --name="CaseMate"
# Save the token!
```

### List All API Tokens
```bash
php artisan api:list-tokens
```

### Revoke API Token
```bash
php artisan api:revoke-token [TOKEN_ID]
```

### Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Rebuild Caches
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Check Application Status
```bash
php artisan about
```

### View Logs
```bash
# Application logs
tail -f storage/logs/laravel.log

# ONSUD import logs
tail -f storage/logs/onsud-import-*.log

# Nginx access logs
tail -f /var/log/nginx/localelogic.uk-access.log

# Nginx error logs
tail -f /var/log/nginx/localelogic.uk-error.log
```

### Database Operations
```bash
# Connect to database
psql -U ploi -d ploi_localelogic

# Check database size
psql -U ploi -d ploi_localelogic -c "SELECT pg_size_pretty(pg_database_size('ploi_localelogic'));"

# Count properties
psql -U ploi -d ploi_localelogic -c "SELECT COUNT(*) FROM properties;"

# Manual backup
pg_dump -U ploi -d ploi_localelogic --format=custom --compress=9 --file=backup_$(date +%Y%m%d).dump
```

### Queue Management
```bash
# Restart queue workers
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry [JOB_ID]

# Clear failed jobs
php artisan queue:flush
```

---

## Troubleshooting Commands

### Permission Fix
```bash
sudo chown -R ploi:ploi storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Reset Application
```bash
php artisan down
composer install --optimize-autoloader --no-dev
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

### Test Database Connection
```bash
php artisan tinker
DB::connection()->getPdo();
exit
```

### Check Redis Connection
```bash
redis-cli ping
# Should return: PONG
```

### Nginx Restart
```bash
sudo systemctl restart nginx
```

### PHP-FPM Restart
```bash
sudo systemctl restart php8.3-fpm
```

---

## Monitoring Commands

### Disk Space
```bash
df -h
du -sh /home/ploi/localelogic.uk
```

### Memory Usage
```bash
free -h
```

### Active Processes
```bash
ps aux | grep php
ps aux | grep nginx
ps aux | grep postgres
```

### PostgreSQL Connections
```bash
psql -U ploi -d ploi_localelogic -c "SELECT count(*) FROM pg_stat_activity;"
```

### Redis Memory
```bash
redis-cli INFO memory
```

---

## Ploi Dashboard Quick Links

- **Deploy**: Site > Deployments > Deploy button
- **Logs**: Site > Deployments > View deployment logs
- **Environment**: Site > Environment (edit .env)
- **Queue Workers**: Site > Queue > Manage workers
- **SSL**: Site > SSL > Let's Encrypt
- **Backups**: Server > Backups > Configure

---

## Emergency Procedures

### Site Down - Enable Maintenance
```bash
php artisan down --retry=60
```

### Site Down - Disable Maintenance
```bash
php artisan up
```

### Rollback Deployment (in Ploi)
```bash
# Go to Deployments tab
# Click on previous successful deployment
# Click "Redeploy"
```

### Restore Database from Backup
```bash
# Drop current database
psql -U ploi -c "DROP DATABASE ploi_localelogic;" postgres
psql -U ploi -c "CREATE DATABASE ploi_localelogic;" postgres

# Restore from backup
pg_restore -U ploi -d ploi_localelogic --no-owner backup_YYYYMMDD.dump
```

---

## Key File Locations

```
/home/ploi/localelogic.uk/                    # Application root
/home/ploi/localelogic.uk/.env                # Environment config
/home/ploi/localelogic.uk/storage/logs/       # Application logs
/home/ploi/localelogic.uk/storage/app/onsud/  # ONSUD data files
/var/log/nginx/                               # Nginx logs
/var/log/postgresql/                          # PostgreSQL logs
~/.pgpass                                     # PostgreSQL password file
```

---

## Performance Checks

### API Response Time Test
```bash
# Test postcode lookup
time curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/postcodes/SN12%206AE

# Should be < 200ms
```

### Database Query Performance
```bash
psql -U ploi -d ploi_localelogic
\timing on
SELECT * FROM properties WHERE pcds = 'SN12 6AE' LIMIT 50;
# Should be < 50ms
```
