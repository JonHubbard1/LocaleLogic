#!/bin/bash

##############################################################################
# LocaleLogic Production Data Copy Script
#
# RUN THIS SCRIPT ON THE PRODUCTION SERVER
# It will pull data from the development server to production
#
# Usage: ./copy-data-to-production.sh
##############################################################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}LocaleLogic Production Data Copy${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

# Configuration - EDIT THESE VALUES
DEV_SERVER="ploi@dev.localelogic.uk"
DEV_DB_HOST="localhost"
DEV_DB_NAME="ploi_localelogic"
DEV_DB_USER="ploi"
DEV_PATH="/home/ploi/dev.localelogic.uk"

PROD_DB_HOST="localhost"
PROD_DB_NAME="ploi_localelogic"
PROD_DB_USER="ploi"
PROD_PATH="/home/ploi/localelogic.uk"  # Adjust this to your production path

# Temporary directory for data transfer
TEMP_DIR="/tmp/localelogic-migration"

echo -e "${YELLOW}Configuration:${NC}"
echo "  Development Server: $DEV_SERVER"
echo "  Development Path: $DEV_PATH"
echo "  Production Path: $PROD_PATH"
echo ""

# Confirm before proceeding
read -p "This will OVERWRITE production data. Are you sure? (yes/no): " -r
echo
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo -e "${RED}Aborted.${NC}"
    exit 1
fi

# Create temporary directory
echo -e "${GREEN}Creating temporary directory...${NC}"
mkdir -p $TEMP_DIR

#############################################################################
# 1. DUMP DATABASE FROM DEVELOPMENT SERVER
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Step 1: Dumping database from development${NC}"
echo -e "${GREEN}============================================${NC}"

ssh $DEV_SERVER << 'ENDSSH'
echo "Dumping PostgreSQL database..."
pg_dump -h localhost -U ploi -d ploi_localelogic \
    --format=custom \
    --compress=9 \
    --file=/tmp/localelogic_db.dump

echo "Database dump size:"
du -h /tmp/localelogic_db.dump
ENDSSH

#############################################################################
# 2. COPY DATABASE DUMP TO PRODUCTION
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Step 2: Copying database dump to production${NC}"
echo -e "${GREEN}============================================${NC}"

echo "Downloading database dump..."
rsync -avz --progress $DEV_SERVER:/tmp/localelogic_db.dump $TEMP_DIR/

#############################################################################
# 3. RESTORE DATABASE ON PRODUCTION
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Step 3: Restoring database on production${NC}"
echo -e "${GREEN}============================================${NC}"

echo "Dropping existing database if it exists..."
psql -h $PROD_DB_HOST -U $PROD_DB_USER -c "DROP DATABASE IF EXISTS $PROD_DB_NAME;" postgres 2>/dev/null || true

echo "Creating fresh database..."
psql -h $PROD_DB_HOST -U $PROD_DB_USER -c "CREATE DATABASE $PROD_DB_NAME;" postgres

echo "Restoring database from dump..."
pg_restore -h $PROD_DB_HOST -U $PROD_DB_USER -d $PROD_DB_NAME \
    --no-owner \
    --no-privileges \
    --verbose \
    $TEMP_DIR/localelogic_db.dump

echo -e "${GREEN}Database restored successfully!${NC}"

#############################################################################
# 4. COPY STORAGE FILES
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Step 4: Copying storage files${NC}"
echo -e "${GREEN}============================================${NC}"

echo "Creating storage directories..."
mkdir -p $PROD_PATH/storage/app/onsud
mkdir -p $PROD_PATH/storage/app/public
mkdir -p $PROD_PATH/storage/logs

echo "Copying ONSUD data files..."
rsync -avz --progress \
    --exclude="livewire-tmp" \
    --exclude="*.log" \
    $DEV_SERVER:$DEV_PATH/storage/app/onsud/ \
    $PROD_PATH/storage/app/onsud/

echo "Copying public storage files..."
rsync -avz --progress \
    $DEV_SERVER:$DEV_PATH/storage/app/public/ \
    $PROD_PATH/storage/app/public/

#############################################################################
# 5. CLEANUP
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Step 5: Cleanup${NC}"
echo -e "${GREEN}============================================${NC}"

echo "Cleaning up temporary files on development server..."
ssh $DEV_SERVER "rm -f /tmp/localelogic_db.dump"

echo "Cleaning up temporary files on production server..."
rm -rf $TEMP_DIR

echo "Setting correct permissions..."
cd $PROD_PATH
chown -R ploi:ploi storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

#############################################################################
# 6. VERIFY
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}Step 6: Verification${NC}"
echo -e "${GREEN}============================================${NC}"

PROPERTY_COUNT=$(psql -h $PROD_DB_HOST -U $PROD_DB_USER -d $PROD_DB_NAME -t -c "SELECT COUNT(*) FROM properties;")
echo "Properties in production database: $PROPERTY_COUNT"

ONSUD_FILES=$(ls -lh $PROD_PATH/storage/app/onsud/ 2>/dev/null | wc -l)
echo "ONSUD files in storage: $ONSUD_FILES"

#############################################################################
# COMPLETE
#############################################################################
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}DATA MIGRATION COMPLETE!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Update .env file with production database credentials"
echo "  2. Run: php artisan config:cache"
echo "  3. Run: php artisan route:cache"
echo "  4. Run: php artisan view:cache"
echo "  5. Test the application"
echo ""
