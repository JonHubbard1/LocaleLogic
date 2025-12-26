# Fixing Production Boundary Import SQL Error

## Problem
The boundary import page at https://localelogic.uk/admin/boundaries shows a SQL error in the "Ward → LAD → County → CED Lookup" section.

## Root Cause
When the database was copied from dev to production, the data and schema were copied, but the production environment may need migrations run to ensure proper schema setup.

## Solution

### Step 1: Commit and Deploy Latest Code
The latest code includes a diagnostic command to check the database status.

```bash
# On dev server
cd /home/ploi/dev.localelogic.uk
git add -A
git commit -m "Add boundary table diagnostic command"
git push origin main
```

### Step 2: Trigger Deployment on Production
In Ploi dashboard:
1. Go to your production site (localelogic.uk)
2. Click "Deploy" to trigger the deployment script
3. The deployment script will automatically run `php artisan migrate --force`

This will ensure all migrations are properly applied to the production database.

### Step 3: Verify the Fix
After deployment completes, run the diagnostic command on production:

```bash
# SSH to production
ssh -p 22 ploi@localelogic.uk

# Navigate to site directory
cd /home/ploi/localelogic.uk

# Run diagnostic
php artisan diagnose:boundary-tables
```

Expected output should show:
- ✓ boundary_imports table exists
- ✓ Constraint includes "lookups" value
- ✓ Query successful

### Step 4: Test the Page
Visit https://localelogic.uk/admin/boundaries and verify the SQL error is gone.

## Alternative Quick Fix (If deployment is not immediate)
If you need to fix this immediately without waiting for deployment:

```bash
# SSH to production
ssh -p 22 ploi@localelogic.uk
cd /home/ploi/localelogic.uk

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## What This Fixes
The migration `2025_12_25_004020_add_lookups_to_boundary_imports_data_type` adds support for the 'lookups' data type to the boundary_imports table. This is required for lookup types like:
- Ward → LAD → County → CED Lookup
- Parish → Ward → LAD Lookup

Without this constraint update, PostgreSQL will reject queries that try to insert or query records with data_type='lookups'.

## Verification
After applying the fix, the boundary import page should:
1. Load without SQL errors
2. Show the Ward → LAD → County → CED Lookup row
3. Display the correct status for any imported lookup data
