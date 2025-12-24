# Properties Table Indexing Strategy

## Overview

Indexes for the `properties` and `properties_staging` tables are **DEFERRED** until after bulk data import for optimal build performance. Creating indexes on a table with 41 million rows is significantly faster when done after the data is loaded rather than during incremental inserts.

## Deferred Indexes

The following indexes should be created in a **separate migration** that is run AFTER the ONSUD bulk data import is complete.

### Individual B-tree Indexes

These indexes support single-column lookups and foreign key queries:

```php
// Postcode lookup
$table->index('pcds', 'idx_properties_pcds');

// Geography code lookups
$table->index('wd25cd', 'idx_properties_wd25cd');
$table->index('ced25cd', 'idx_properties_ced25cd');
$table->index('parncp25cd', 'idx_properties_parncp25cd');
$table->index('lad25cd', 'idx_properties_lad25cd');
$table->index('pcon24cd', 'idx_properties_pcon24cd');
$table->index('rgn25cd', 'idx_properties_rgn25cd');
$table->index('pfa23cd', 'idx_properties_pfa23cd');
```

### Composite Indexes

These indexes support common query patterns combining geography and postcode:

```php
// Parish + Postcode (e.g., "all properties in parish X with postcode Y")
$table->index(['parncp25cd', 'pcds'], 'idx_properties_parish_postcode');

// LAD + Postcode (e.g., "all properties in LAD X with postcode Y")
$table->index(['lad25cd', 'pcds'], 'idx_properties_lad_postcode');

// Ward + Postcode (e.g., "all properties in ward X with postcode Y")
$table->index(['wd25cd', 'pcds'], 'idx_properties_ward_postcode');
```

## Migration File to Create

Create a new migration file: `YYYY_MM_DD_HHMMSS_create_properties_indexes.php`

This migration should:
1. Be run AFTER bulk ONSUD data import
2. Create all individual B-tree indexes
3. Create all composite indexes
4. Include estimated time warnings (may take 30+ minutes for 41M rows)
5. Be reversible (drop indexes in down() method)

## Example Migration Structure

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Properties Table Indexes
 *
 * Creates all deferred indexes on properties table after bulk data import.
 * WARNING: This migration may take 30+ minutes to complete on 41M rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Individual B-tree indexes
            $table->index('pcds', 'idx_properties_pcds');
            $table->index('wd25cd', 'idx_properties_wd25cd');
            $table->index('ced25cd', 'idx_properties_ced25cd');
            $table->index('parncp25cd', 'idx_properties_parncp25cd');
            $table->index('lad25cd', 'idx_properties_lad25cd');
            $table->index('pcon24cd', 'idx_properties_pcon24cd');
            $table->index('rgn25cd', 'idx_properties_rgn25cd');
            $table->index('pfa23cd', 'idx_properties_pfa23cd');

            // Composite indexes
            $table->index(['parncp25cd', 'pcds'], 'idx_properties_parish_postcode');
            $table->index(['lad25cd', 'pcds'], 'idx_properties_lad_postcode');
            $table->index(['wd25cd', 'pcds'], 'idx_properties_ward_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Drop indexes in reverse order
            $table->dropIndex('idx_properties_ward_postcode');
            $table->dropIndex('idx_properties_lad_postcode');
            $table->dropIndex('idx_properties_parish_postcode');

            $table->dropIndex('idx_properties_pfa23cd');
            $table->dropIndex('idx_properties_rgn25cd');
            $table->dropIndex('idx_properties_pcon24cd');
            $table->dropIndex('idx_properties_lad25cd');
            $table->dropIndex('idx_properties_parncp25cd');
            $table->dropIndex('idx_properties_ced25cd');
            $table->dropIndex('idx_properties_wd25cd');
            $table->dropIndex('idx_properties_pcds');
        });
    }
};
```

## Usage

1. Run migrations up to and including `create_properties_staging_table`
2. Import ONSUD data into `properties_staging` table (no indexes = faster import)
3. Run index creation migration on staging table
4. Validate staging data using TableSwapService
5. Swap staging table to production using TableSwapService
6. Drop old properties table to free space

## Performance Notes

- Index creation on 41M rows can take 30-60 minutes depending on hardware
- Run during maintenance window when API traffic is low
- Monitor disk space (indexes may require 10-20GB additional storage)
- Consider using PostgreSQL CONCURRENTLY option in production to avoid table locks
