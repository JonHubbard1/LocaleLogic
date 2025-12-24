# Geography Lookup Data

This document explains how to populate the geography lookup tables with ONS (Office for National Statistics) data.

## Quick Start (Development/Testing)

For development and testing, use the provided seeder with sample data:

```bash
php artisan db:seed --class=GeographyLookupSeeder
```

This will populate all lookup tables with ~70 sample geography records covering England, Wales, and Scotland.

## Production Data Import

For production, you should import the full ONS Names and Codes lookup files.

### 1. Download ONS Lookup Files

Download the latest Names and Codes files from the ONS Open Geography Portal:
https://geoportal.statistics.gov.uk/

Required files (December 2025 editions):
- **Regions**: `Regions_December_2025_EN_NC.csv`
- **Counties**: `Counties_December_2025_EN_NC.csv`
- **Local Authority Districts**: `LAD_December_2025_UK_NC.csv`
- **Wards**: `Ward_December_2025_UK_NC.csv`
- **County Electoral Divisions**: `CED_December_2025_EN_NC.csv`
- **Parishes**: `Parish_December_2025_EW_NC.csv`
- **Westminster Constituencies**: `Westminster_Parliamentary_Constituencies_December_2024_UK_NC.csv`
- **Police Force Areas**: `Police_Force_Areas_December_2023_EN_NC.csv`

### 2. Place Files in Storage

Create a directory and place the CSV files:

```bash
mkdir -p storage/app/geography
# Copy your CSV files to storage/app/geography/
```

### 3. Import Using Command

Import all geography types at once:

```bash
php artisan geography:import --all --directory=geography --truncate
```

Or import individual types:

```bash
php artisan geography:import --file=storage/app/geography/Regions_December_2025_EN_NC.csv --type=regions
php artisan geography:import --file=storage/app/geography/Counties_December_2025_EN_NC.csv --type=counties
php artisan geography:import --file=storage/app/geography/LAD_December_2025_UK_NC.csv --type=lads
php artisan geography:import --file=storage/app/geography/Ward_December_2025_UK_NC.csv --type=wards
php artisan geography:import --file=storage/app/geography/CED_December_2025_EN_NC.csv --type=ceds
php artisan geography:import --file=storage/app/geography/Parish_December_2025_EW_NC.csv --type=parishes
php artisan geography:import --file=storage/app/geography/Westminster_Parliamentary_Constituencies_December_2024_UK_NC.csv --type=constituencies
php artisan geography:import --file=storage/app/geography/Police_Force_Areas_December_2023_EN_NC.csv --type=police
```

## Check Import Status

View the status of all geography lookup tables:

```bash
php artisan geography:status
```

View detailed data for a specific table:

```bash
php artisan geography:status --table=wards
php artisan geography:status --table=lads
php artisan geography:status --table=constituencies
```

## CSV File Formats

### Expected CSV Columns

**Regions**:
- Column 0: `rgn25cd` (9-char GSS code, e.g., E12000001)
- Column 1: `rgn25nm` (Region name)

**Counties**:
- Column 0: `cty25cd` (9-char GSS code)
- Column 1: `cty25nm` (County name)

**Local Authority Districts**:
- Column 0: `lad25cd` (9-char GSS code)
- Column 1: `lad25nm` (LAD name in English)
- Column 2: `lad25nmw` (LAD name in Welsh, optional)
- Column 3: `rgn25cd` (Parent region code, optional)

**Wards**:
- Column 0: `wd25cd` (9-char GSS code)
- Column 1: `wd25nm` (Ward name)
- Column 2: `lad25cd` (Parent LAD code, required)

**County Electoral Divisions**:
- Column 0: `ced25cd` (9-char GSS code)
- Column 1: `ced25nm` (CED name)
- Column 2: `cty25cd` (Parent county code, optional)

**Parishes**:
- Column 0: `parncp25cd` (9-char GSS code)
- Column 1: `parncp25nm` (Parish name in English)
- Column 2: `parncp25nmw` (Parish name in Welsh, optional)
- Column 3: `lad25cd` (Parent LAD code, required)

**Westminster Constituencies**:
- Column 0: `pcon24cd` (9-char GSS code, December 2024 boundaries)
- Column 1: `pcon24nm` (Constituency name)

**Police Force Areas**:
- Column 0: `pfa23cd` (9-char GSS code, December 2023 boundaries)
- Column 1: `pfa23nm` (Police force area name)

## Import Order

Due to foreign key constraints, import in this order:

1. Regions (no dependencies)
2. Counties (no dependencies)
3. Local Authority Districts (depends on regions)
4. Wards (depends on LADs)
5. County Electoral Divisions (depends on counties)
6. Parishes (depends on LADs)
7. Constituencies (no dependencies)
8. Police Force Areas (no dependencies)

The `--all` option handles this order automatically.

## Sample Record Counts

After full ONS data import, you should see approximately:

| Table          | Expected Records |
|----------------|------------------|
| Regions        | 9-12             |
| Counties       | 26               |
| LADs           | 350-400          |
| Wards          | 9,000-10,000     |
| CEDs           | 1,400-1,600      |
| Parishes       | 10,000-11,000    |
| Constituencies | 650              |
| Police         | 44               |

## Troubleshooting

### Foreign Key Violations

If you see foreign key errors during ward or parish import, ensure parent tables (LADs, regions) are populated first.

The import command will skip records with missing parent references and show warnings.

### Duplicate Key Errors

Use `--truncate` option to clear existing data before import:

```bash
php artisan geography:import --all --directory=geography --truncate
```

### File Not Found

Ensure CSV files are in the correct location:
- For `--all`: `storage/app/<directory>/`
- For individual imports: Use full path or path relative to project root

## Updating Geography Data

ONS publishes updated lookup files periodically (usually annually or after boundary changes).

To update:

1. Download latest CSV files from ONS
2. Run import with `--truncate` to replace existing data
3. Verify counts with `php artisan geography:status`

**Note**: If you update lookups, you should also re-import ONSUD data to ensure geography codes align with the latest boundaries.
