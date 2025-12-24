# ONSUD Data Import Guide

Complete guide for importing ONS UPRN Directory (ONSUD) data into the LocaleLogic system.

## Table of Contents

- [Overview](#overview)
- [Import Methods](#import-methods)
- [Option 1: Automatic Download](#option-1-automatic-download-recommended)
- [Option 2: Manual Upload](#option-2-manual-upload)
- [Import Process](#import-process)
- [Advanced Options](#advanced-options)
- [Troubleshooting](#troubleshooting)

---

## Overview

The ONSUD dataset contains comprehensive postcode and property data for the UK, including:
- Unique Property Reference Numbers (UPRNs)
- Postcodes (PCDS)
- Grid coordinates (Easting/Northing)
- Administrative geography codes
- Over 41 million property records

**Release Schedule**: Quarterly (February, May, August, November)

---

## Import Methods

You have **two options** for importing ONSUD data:

### **Option 1: Automatic Download**
~~The system attempts to download the file directly from ONS data sources.~~

**NOT CURRENTLY SUPPORTED.** The ONS portal requires manual download via browser.

### **Option 2: Manual Upload** (Recommended)
Download the file yourself from the ONS Open Geography Portal and provide the path to the import command.

---

## Option 1: Automatic Download

### Important Note

**Automatic download is NOT currently supported for ONSUD.**

The ONS Open Geography Portal uses ArcGIS Hub, which:
- Generates downloads on-demand via interactive browser sessions
- Does not provide direct/static download URLs for large datasets
- Requires JavaScript to trigger file exports

**Recommendation:** Use **Option 2: Manual Upload** instead.

### Why Automatic Download Doesn't Work

ONSUD files are very large (2-3GB, 41M+ records), and the ONS portal:
1. Generates files on-demand when you click "Download"
2. Requires JavaScript/browser interaction
3. No simple HTTP URLs exist for direct file access

**Future Enhancement:** Could be implemented using browser automation (Selenium/Playwright), but this adds significant complexity.

### What Happens If You Try

If you run the command without `--file`, you'll see:

```
Attempting automatic download of ONSUD epoch 114...
Automatic download not available for ONSUD.
ONSUD files are very large (2-3GB) and must be downloaded manually.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  MANUAL DOWNLOAD REQUIRED
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[Step-by-step manual download instructions shown]
```

**Proceed to Option 2: Manual Upload below.**

---

## Option 2: Manual Upload (Recommended)

### Step 1: Download ONSUD File

**Official Source**: ONS Open Geography Portal

1. **Visit the portal**: https://geoportal.statistics.gov.uk/

2. **Search for the dataset**:
   - In the search box, enter: `ONSUD epoch 114` or `ONS UPRN Directory`
   - Look for the specific epoch/date you need (e.g., "November 2024 Epoch 114")

3. **Open the dataset page**:
   - Click on the matching dataset
   - Example URL format: `https://geoportal.statistics.gov.uk/datasets/ons::ons-uprn-directory-november-2024-epoch-114`

4. **Download the file**:
   - Click the **"Download"** button on the right side
   - Select **"Spreadsheet"** or **"CSV"** format
   - The portal will generate the export (may take 5-10 minutes for large files)
   - Download the generated file (usually 2-3 GB ZIP or 8-10 GB CSV)

5. **File naming**:
   - ZIP files typically named: `ONSUD_NOV24_EP114.zip`
   - CSV files typically named: `ONSUD_NOV_2024.csv` or similar

**Alternative access**:
- Via data.gov.uk (redirects to the portal): https://www.data.gov.uk/
- Search for "ONSUD" or "ONS UPRN Directory"

### Step 2: Upload to Server

Transfer the file to your server:

```bash
# Using SCP
scp ONSUD_NOV24_EP114.zip user@server:/home/ploi/dev.localelogic.uk/storage/app/onsud/

# Or use SFTP, rsync, etc.
```

### Step 3: Run Import Command

**Option A: With ZIP file** (Recommended - supports multiple regional files)
```bash
php artisan onsud:import \
  --file=/home/ploi/dev.localelogic.uk/storage/app/onsud/ONSUD_NOV24_EP114.zip \
  --epoch=114 \
  --release-date=2024-11-01
```

✅ **Supports multiple CSV files**: If the ZIP contains multiple regional CSV files (e.g., 12 regional files), the import command will automatically detect and process all of them sequentially.

**Option B: With single extracted CSV**
```bash
# If you've already extracted and only have one CSV file
php artisan onsud:import \
  --file=/home/ploi/dev.localelogic.uk/storage/app/onsud/ONSUD_NOV24_EP114.csv \
  --epoch=114 \
  --release-date=2024-11-01
```

**Note on Regional Files**: ONSUD data is often split into multiple regional CSV files within a single ZIP. The import command handles this automatically - just provide the ZIP file path and it will process all CSV files it finds.

---

## Import Process

### What Happens During Import

1. **Validation**
   - Checks epoch and release date are provided
   - Validates file exists and is accessible
   - Verifies CSV header contains required columns

2. **Download/Extract** (if needed)
   - Downloads file if using auto-download (not currently supported)
   - Extracts ZIP if compressed
   - Locates all CSV files (supports multiple regional files)

3. **Data Version Tracking**
   - Creates record in `data_versions` table
   - Tracks import progress and status

4. **Staging Import**
   - Truncates `properties_staging` table (once at the start)
   - **Processes each CSV file sequentially** (if multiple regional files)
   - Imports each CSV in batches (10,000 rows default)
   - Converts OS Grid coordinates to WGS84
   - Validates data quality
   - Shows progress bar for each file
   - Aggregates statistics across all files

5. **Statistics Display**
   ```
   Found 12 CSV files to process:
     1. ONSUD_NOV24_EP114_Part1.csv (850.5 MB)
     2. ONSUD_NOV24_EP114_Part2.csv (823.2 MB)
     ...
     12. ONSUD_NOV24_EP114_Part12.csv (798.1 MB)

   Processing file 1 of 12: ONSUD_NOV24_EP114_Part1.csv
   Processing 3,456,789 rows...
   [Progress bar]

   Processing file 2 of 12: ONSUD_NOV24_EP114_Part2.csv
   Processing 3,234,123 rows...
   [Progress bar]

   ... [continuing for all files]

   Import Statistics:
   +-------------------------+---------------+
   | Metric                  | Count         |
   +-------------------------+---------------+
   | Total Rows              | 41,234,567    |
   | Successful              | 41,180,234    |
   | Skipped                 | 54,333        |
   | Coordinate Errors       | 12,145        |
   | Missing Required Fields | 42,188        |
   +-------------------------+---------------+
   Success Rate: 99.87%
   ```

6. **Table Swap**
   - Validates staging table
   - Atomic swap: `properties_staging` → `properties`
   - Old table renamed to `properties_old`

7. **Index Creation** (30-60 minutes for large datasets)
   - Creates 11 optimized indexes
   - Includes single and composite indexes
   - Progress bar shows completion

8. **Cleanup**
   - Updates data version status to "current"
   - Archives previous versions
   - Optionally drops old table

### Typical Timeline

| Step | Duration | Notes |
|------|----------|-------|
| Download | 5-15 min | Depends on connection speed |
| Extraction | 1-2 min | |
| CSV Import | 45-90 min | ~450,000 rows/minute |
| Table Swap | 5-10 sec | Atomic operation |
| Index Creation | 30-60 min | Can be skipped initially |
| **Total** | **90-180 min** | Approximately 2-3 hours |

---

## Working with Multiple Regional Files

### Background

ONSUD data from ONS is often distributed as multiple regional CSV files within a single ZIP archive (typically 10-12 files). This is done because:
- The full UK dataset is very large (41M+ records, 8-10GB uncompressed)
- Regional splits make the data more manageable
- Each file covers a specific geographic region (e.g., England North, England South, Scotland, Wales, etc.)

### How the Import Handles Multiple Files

The import command automatically:

1. **Detects all CSV files** in the ZIP archive
2. **Lists them** with file sizes for your reference
3. **Processes each file sequentially** into the same staging table
4. **Aggregates statistics** across all files
5. **Performs a single table swap** after all files are imported

### Example Output

```bash
$ php artisan onsud:import --file=ONSUD_NOV24_EP114.zip --epoch=114 --release-date=2024-11-01

Extracting ZIP file...
Extraction complete
Found 12 CSV files (ONSUD regional files)

Found 12 CSV files to process:
  1. ONSUD_NOV24_Part1_England_North.csv (850.5 MB)
  2. ONSUD_NOV24_Part2_England_South.csv (823.2 MB)
  3. ONSUD_NOV24_Part3_London.csv (612.8 MB)
  4. ONSUD_NOV24_Part4_Scotland.csv (445.3 MB)
  5. ONSUD_NOV24_Part5_Wales.csv (298.7 MB)
  ... and 7 more files

Processing file 1 of 12: ONSUD_NOV24_Part1_England_North.csv
[Progress bar showing 3.4M rows]

Processing file 2 of 12: ONSUD_NOV24_Part2_England_South.csv
[Progress bar showing 3.2M rows]

... continues for all 12 files ...

Import Statistics:
  Total Rows: 41,234,567 (across all 12 files)
  Successful: 41,180,234
  Success Rate: 99.87%
```

### Important Notes

✅ **All files are processed** - You don't need to do anything special; just provide the ZIP file
✅ **Single staging table** - All regional data goes into one table (properties_staging)
✅ **One table swap** - Only one atomic swap happens after all files are imported
✅ **Aggregated statistics** - Final stats show totals across all files

⚠️ **Disk space** - Ensure you have enough space for:
- ZIP file: ~2-3 GB
- Extracted CSVs: ~8-10 GB total
- Staging table: ~15-20 GB

### If You Only Have Individual CSVs

If you've already extracted the ZIP and want to import individual regional files, you have two options:

**Option 1: Re-zip them** (Recommended)
```bash
zip ONSUD_combined.zip Part*.csv
php artisan onsud:import --file=ONSUD_combined.zip --epoch=114 --release-date=2024-11-01
```

**Option 2: Import each file separately** (Not recommended - requires manual merging)
```bash
# This would require multiple imports and manual data management
# Better to use Option 1 above
```

---

## Advanced Options

### Skip Table Swap

Import to staging only without swapping to production:

```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --skip-swap
```

**Use case**: Test import process, validate data quality

---

### Skip Index Creation

Swap tables but don't create indexes (create them later manually):

```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --skip-indexes
```

**Use case**: Speed up import, create indexes during off-peak hours

**Create indexes later:**
```bash
php artisan db:seed --class=PropertiesIndexSeeder
# Or run migrations
```

---

### Custom Batch Size

Adjust import batch size (default: 10,000):

```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --batch-size=5000
```

**Smaller batches**: Lower memory usage, slower import
**Larger batches**: Higher memory usage, faster import

---

### Force Import

Continue even if validation fails:

```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --force
```

**Warning**: Use with caution. May import partial/invalid data.

---

### Cleanup Old Table

Immediately drop the old properties table after successful swap:

```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --cleanup-old
```

**Without this flag**: Old table retained as `properties_old` for rollback

---

### Use Existing Download

Skip download and use already extracted file:

```bash
php artisan onsud:import \
  --epoch=114 \
  --release-date=2024-11-01 \
  --skip-download
```

**Requirement**: File must already exist at:
```
storage/app/onsud/epoch-114/extracted/*.csv
```

---

## Troubleshooting

### Issue: Auto-Download Fails

**Error**: "Automatic download failed or file not available"

**Solutions**:
1. Use manual upload method (Option 2)
2. Check server has internet access
3. Verify epoch number is correct
4. Check ONS website for file availability

---

### Issue: Out of Disk Space

**Error**: "No space left on device"

**Check Space**:
```bash
df -h /home/ploi/dev.localelogic.uk/storage
```

**Requirements**:
- ZIP file: ~2-3 GB
- Extracted CSV: ~8-10 GB
- Staging table: ~15-20 GB
- **Total needed**: ~40-50 GB free space

**Solutions**:
1. Clear old ONSUD downloads:
   ```bash
   php artisan onsud:cleanup --downloads
   ```
2. Drop old tables:
   ```bash
   php artisan onsud:cleanup --old-tables
   ```

---

### Issue: Import Times Out

**Error**: Process killed or timeout

**Solutions**:
1. Run in screen/tmux session:
   ```bash
   screen -S onsud-import
   php artisan onsud:import ...
   # Ctrl+A, D to detach
   # screen -r onsud-import to reattach
   ```

2. Increase PHP timeout in `.env`:
   ```env
   MAX_EXECUTION_TIME=7200  # 2 hours
   ```

---

### Issue: Missing Required Columns

**Error**: "CSV header missing required columns"

**Required Columns**:
- UPRN
- PCDS
- GRIDGB1E
- GRIDGB1N
- LAD25CD

**Solution**: Verify you're using official ONSUD file, not a subset

---

### Issue: Coordinate Conversion Errors

**Warning**: "Coordinate Errors: 12,145"

**Explanation**: Some coordinates outside valid UK bounds

**Normal**: 0.01-0.1% errors expected (offshore, invalid data)
**Concerning**: >1% errors - verify data source

---

### Issue: Table Swap Fails

**Error**: "Table swap failed: Validation failed"

**Checks**:
1. Staging table record count
2. Data quality issues
3. Constraints violations

**Recovery**:
```bash
# Rollback to previous version
php artisan onsud:rollback

# Re-import with --force
php artisan onsud:import ... --force
```

---

## Monitoring Import Progress

### View Real-Time Progress

The import command shows:
- Download progress bar
- Extraction status
- Row processing progress
- Statistics summary

### Check Import Status

```bash
# View data version status
php artisan onsud:status

# Check logs
tail -f storage/logs/laravel.log | grep ONSUD
```

### Database Check

```bash
# Row count in staging
psql -U forge dev_localelogic_uk -c "SELECT COUNT(*) FROM properties_staging;"

# Latest data version
psql -U forge dev_localelogic_uk -c "SELECT * FROM data_versions ORDER BY id DESC LIMIT 1;"
```

---

## Best Practices

1. **Test First**: Import to staging with `--skip-swap` to verify data
2. **Backup**: Create database backup before production swap
3. **Off-Peak**: Run during low-traffic hours (30-60 min downtime)
4. **Monitor**: Watch logs and progress closely
5. **Cleanup**: Remove old files after successful import

---

## Quick Reference

### Manual Upload Import (Recommended)
```bash
php artisan onsud:import \
  --file=/path/to/ONSUD_NOV24_EP114.zip \
  --epoch=114 \
  --release-date=2024-11-01
```

### Auto-Download Import (Not Supported)
```bash
# This will show manual download instructions:
php artisan onsud:import --epoch=114 --release-date=2024-11-01
```

### Test Import (No Swap)
```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --skip-swap
```

### Fast Import (No Indexes)
```bash
php artisan onsud:import \
  --file=/path/to/file.zip \
  --epoch=114 \
  --release-date=2024-11-01 \
  --skip-indexes
```

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Run status: `php artisan onsud:status`
- Cleanup: `php artisan onsud:cleanup --help`
