# Task 2: Geography Lookup Tables and Migrations

## Overview
**Task Reference:** Task #2 from `/home/ploi/dev.localelogic.uk/agent-os/specs/2025-12-23-database-schema-and-core-models/tasks.md`
**Implemented By:** Database Engineer
**Date:** 2025-12-23
**Status:** Complete

### Task Description
Implement the geography lookup tables layer for the LocaleLogic database schema by creating 8 Laravel migrations for ONS geography reference data (regions, counties, LADs, wards, CEDs, parishes, constituencies, police force areas) and their corresponding Eloquent models. This layer provides the foundational lookup tables that will be referenced by the 41 million property records to translate geography codes into human-readable names.

## Implementation Summary
Successfully implemented all 8 geography lookup table migrations in proper dependency order, ensuring foreign key relationships and indexes are correctly defined. Created corresponding Eloquent models with custom primary keys (GSS codes instead of auto-incrementing IDs) and established bidirectional relationships between parent and child geographies. All tables include standard Laravel timestamps for auditing, with no soft delete functionality as per specification.

The implementation uses Laravel's Schema Builder to create migration files that can be executed to build the database schema. Each migration includes both `up()` and `down()` methods for reversibility. Models are configured with non-incrementing string primary keys to match ONS GSS coding standards, and relationships are explicitly defined with foreign key column specifications.

All 8 focused tests pass successfully, validating model configuration, table names, primary keys, and critical relationships (belongsTo and hasMany).

## Files Changed/Created

### New Files
- `/home/ploi/dev.localelogic.uk/app/Models/Region.php` - Eloquent model for UK regions (12 records)
- `/home/ploi/dev.localelogic.uk/app/Models/County.php` - Eloquent model for UK counties (30 records)
- `/home/ploi/dev.localelogic.uk/app/Models/LocalAuthorityDistrict.php` - Eloquent model for LADs with Welsh name support (350 records)
- `/home/ploi/dev.localelogic.uk/app/Models/Ward.php` - Eloquent model for electoral wards (9,000 records)
- `/home/ploi/dev.localelogic.uk/app/Models/CountyElectoralDivision.php` - Eloquent model for CEDs (1,400 records)
- `/home/ploi/dev.localelogic.uk/app/Models/Parish.php` - Eloquent model for parishes with Welsh name support (11,000 records)
- `/home/ploi/dev.localelogic.uk/app/Models/Constituency.php` - Eloquent model for Westminster constituencies (650 records)
- `/home/ploi/dev.localelogic.uk/app/Models/PoliceForceArea.php` - Eloquent model for police force areas (44 records)
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000001_create_regions_table.php` - Migration for regions lookup table
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000002_create_counties_table.php` - Migration for counties lookup table
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000003_create_local_authority_districts_table.php` - Migration for LADs with foreign key to regions
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000004_create_wards_table.php` - Migration for wards with foreign key to LADs
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000005_create_county_electoral_divisions_table.php` - Migration for CEDs with foreign key to counties
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000006_create_parishes_table.php` - Migration for parishes with foreign key to LADs
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000007_create_constituencies_table.php` - Migration for constituencies (independent table)
- `/home/ploi/dev.localelogic.uk/database/migrations/2025_12_23_000008_create_police_force_areas_table.php` - Migration for police force areas (independent table)
- `/home/ploi/dev.localelogic.uk/tests/Unit/Models/LookupTableModelsTest.php` - 8 focused tests for lookup table models

### Modified Files
None - This is new implementation building on Task Group 1's foundation.

### Deleted Files
None

## Key Implementation Details

### Migration Dependency Order
**Location:** `/home/ploi/dev.localelogic.uk/database/migrations/`

All 8 migrations were created with timestamp prefixes (2025_12_23_00000X) to ensure correct execution order:

1. **Regions** (000001) - No dependencies, creates root-level geography
2. **Counties** (000002) - No dependencies, parallel root-level geography
3. **Local Authority Districts** (000003) - Foreign key to regions
4. **Wards** (000004) - Foreign key to LADs
5. **County Electoral Divisions** (000005) - Foreign key to counties
6. **Parishes** (000006) - Foreign key to LADs
7. **Constituencies** (000007) - Independent, no foreign keys
8. **Police Force Areas** (000008) - Independent, no foreign keys

**Rationale:** This ordering ensures parent tables exist before child tables that reference them via foreign keys, preventing migration failures. Migrations 1-2 can run in parallel, then 3 depends on 1, then 4 and 6 depend on 3, and 5 depends on 2. Migrations 7-8 are independent and could run at any point.

### Foreign Key Constraints and Indexes
**Location:** Various migration files

Each migration that establishes a parent-child relationship includes:
- Foreign key constraint with `onDelete('set null')` or `onDelete('cascade')` behavior
- Index on the foreign key column for efficient relationship queries

For example, in `create_wards_table.php`:
```php
$table->foreign('lad25cd')
      ->references('lad25cd')
      ->on('local_authority_districts')
      ->onDelete('cascade');

$table->index('lad25cd', 'idx_wards_lad25cd');
```

**Rationale:** Foreign keys enforce referential integrity at the database level, ensuring wards cannot reference non-existent LADs. Indexes on foreign key columns dramatically improve JOIN query performance when traversing relationships. The `onDelete('cascade')` behavior ensures that if a parent geography is deleted, child records are automatically removed, maintaining data consistency.

### Custom Primary Keys (GSS Codes)
**Location:** All model files

Every lookup model uses its ONS GSS code as the primary key instead of Laravel's default auto-incrementing `id`:

```php
protected $primaryKey = 'rgn25cd';  // For Region model
protected $keyType = 'string';
public $incrementing = false;
```

**Rationale:** ONS GSS codes are globally unique identifiers assigned by the Office for National Statistics. Using them as primary keys eliminates the need for separate ID columns and makes it easier to join data from external ONS sources. The codes are stable and won't change, making them ideal candidates for primary keys. This approach also matches the specification requirement to use GSS codes for all geography identifiers.

### Welsh Language Name Support
**Location:** `LocalAuthorityDistrict.php` and `Parish.php` models/migrations

The LAD and Parish tables include nullable Welsh name columns (`lad25nmw`, `parncp25nmw`):

```php
$table->string('lad25nmw', 100)->nullable()->comment('LAD name (Welsh)');
```

**Rationale:** Per specification requirements, some UK geographies have official Welsh language names (particularly in Wales). These are stored in separate columns rather than using a translation system, as they are part of the source ONS data and should be preserved exactly as provided. The nullable constraint allows English-only geographies to omit Welsh names.

### Model Relationships
**Location:** All model files

Each model defines bidirectional relationships using Laravel's Eloquent ORM:

- **belongsTo:** Child models define relationships to their parents (e.g., Ward -> LAD)
- **hasMany:** Parent models define relationships to their children (e.g., LAD -> wards)

Example from Ward model:
```php
public function localAuthorityDistrict()
{
    return $this->belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd');
}
```

Example from LocalAuthorityDistrict model:
```php
public function wards()
{
    return $this->hasMany(Ward::class, 'lad25cd', 'lad25cd');
}
```

**Rationale:** Explicit foreign key column specification is required because we're using custom primary keys instead of Laravel's default `id` column. These relationships enable intuitive navigation through the geography hierarchy (e.g., `$ward->localAuthorityDistrict->region->rgn25nm`) and support efficient eager loading to avoid N+1 query problems.

### Test Implementation Strategy
**Location:** `/home/ploi/dev.localelogic.uk/tests/Unit/Models/LookupTableModelsTest.php`

Tests use Illuminate\Database\Capsule\Manager for lightweight in-memory SQLite database setup instead of full Laravel test infrastructure:

```php
public static function setUpBeforeClass(): void
{
    self::$capsule = new Capsule;
    self::$capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);
    self::$capsule->setAsGlobal();
    self::$capsule->bootEloquent();

    self::createTables();
}
```

**Rationale:** This approach avoids the complexity of setting up a full Laravel application environment while still validating model behavior. The in-memory SQLite database provides fast test execution without requiring external database setup. Tests validate critical behaviors (primary key configuration, table names, relationships) without exhaustively testing every edge case, per the minimal testing standards.

## Database Changes

### Migrations
- `2025_12_23_000001_create_regions_table.php` - Creates regions table with rgn25cd primary key
  - Added tables: regions
  - Added indexes: primary key on rgn25cd
- `2025_12_23_000002_create_counties_table.php` - Creates counties table with cty25cd primary key
  - Added tables: counties
  - Added indexes: primary key on cty25cd
- `2025_12_23_000003_create_local_authority_districts_table.php` - Creates LADs table with Welsh name support
  - Added tables: local_authority_districts
  - Added indexes: primary key on lad25cd, index on rgn25cd
  - Added foreign keys: rgn25cd -> regions(rgn25cd)
- `2025_12_23_000004_create_wards_table.php` - Creates wards table
  - Added tables: wards
  - Added indexes: primary key on wd25cd, index on lad25cd
  - Added foreign keys: lad25cd -> local_authority_districts(lad25cd)
- `2025_12_23_000005_create_county_electoral_divisions_table.php` - Creates CEDs table
  - Added tables: county_electoral_divisions
  - Added indexes: primary key on ced25cd, index on cty25cd
  - Added foreign keys: cty25cd -> counties(cty25cd)
- `2025_12_23_000006_create_parishes_table.php` - Creates parishes table with Welsh name support
  - Added tables: parishes
  - Added indexes: primary key on parncp25cd, index on lad25cd
  - Added foreign keys: lad25cd -> local_authority_districts(lad25cd)
- `2025_12_23_000007_create_constituencies_table.php` - Creates constituencies table
  - Added tables: constituencies
  - Added indexes: primary key on pcon24cd
- `2025_12_23_000008_create_police_force_areas_table.php` - Creates police force areas table
  - Added tables: police_force_areas
  - Added indexes: primary key on pfa23cd

### Schema Impact
All tables include:
- Custom string primary keys (GSS codes) instead of auto-incrementing IDs
- Standard Laravel timestamps (created_at, updated_at) for auditing
- No soft delete columns (per specification)
- Appropriate foreign key constraints for hierarchical relationships
- Indexes on all foreign key columns for query performance

The schema establishes a hierarchy:
- Regions (root) -> LADs -> Wards
- Regions (root) -> LADs -> Parishes
- Counties (root) -> CEDs
- Constituencies (independent)
- Police Force Areas (independent)

## Dependencies
No new dependencies added - this task uses existing Laravel framework and database components installed in Task Group 1.

## Testing

### Test Files Created/Updated
- `/home/ploi/dev.localelogic.uk/tests/Unit/Models/LookupTableModelsTest.php` - 8 focused tests covering model configuration and relationships

### Test Coverage
- Unit tests: Complete (8 tests covering all critical model behaviors)
- Integration tests: Not applicable at this stage
- Edge cases covered:
  - Region model has correct table name and primary key configuration
  - LAD establishes belongsTo relationship to Region
  - Ward establishes belongsTo relationship to LAD
  - LAD hasMany Wards relationship returns collection
  - CED establishes belongsTo relationship to County
  - Parish supports Welsh language names (nullable column)
  - Constituency and PFA models have correct table names
  - All lookup models have timestamps enabled

### Manual Testing Performed
Executed test suite using PHPUnit:
```bash
./vendor/bin/phpunit tests/Unit/Models/LookupTableModelsTest.php --testdox
```

Results: 8 tests, 30 assertions, all passing in 0.058 seconds.

Tests validate:
- All models use correct table names
- All models use correct GSS code primary keys
- All models are configured as non-incrementing string keys
- Relationships correctly navigate parent-child hierarchies
- Welsh name columns are nullable and properly populated
- All models have timestamps enabled

## User Standards & Preferences Compliance

### Backend Migrations Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/backend/migrations.md`

**How Implementation Complies:**
All migrations implement reversible up/down methods for safe rollback capability. Each migration is focused on a single table creation for clarity. Migration filenames use clear, descriptive names indicating what they create. Foreign key relationships are defined at the database level to enforce data integrity. Indexes are created on foreign key columns to optimize relationship queries.

**Deviations:** None

### Backend Models Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/backend/models.md`

**How Implementation Complies:**
Model names are singular while table names are plural, following Laravel conventions. All lookup tables include created_at and updated_at timestamps for auditing. Database constraints (NOT NULL, foreign keys) are enforced at the database level through migrations. Appropriate data types are chosen (CHAR(9) for fixed-length GSS codes, VARCHAR for variable-length names). Indexes are created on all foreign key columns. Relationships are clearly defined with explicit cascade behaviors.

**Deviations:** None

### Global Coding Style Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/global/coding-style.md`

**How Implementation Complies:**
Method names are descriptive and follow Laravel conventions (localAuthorityDistrict(), countyElectoralDivisions()). Class and method structure is consistent across all 8 models. Code includes PHPDoc comments explaining model purpose and relationships. No dead code or commented-out blocks are present.

**Deviations:** None

### Global Conventions Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/global/conventions.md`

**How Implementation Complies:**
Files are organized in Laravel's standard structure (app/Models/, database/migrations/). Migration filenames include timestamps to ensure correct execution order. The specification's requirements are fully documented in this implementation file.

**Deviations:** None

### Testing Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/testing/test-writing.md`

**How Implementation Complies:**
Wrote only 8 focused tests covering critical model configuration and key relationships. Tests focus on behavior (model retrieval, relationships work) rather than implementation details. Deferred exhaustive edge case testing per the standard. Tests execute quickly (58ms) for fast feedback during development.

**Deviations:** None

## Integration Points

### APIs/Endpoints
Not applicable - This task creates database schema and models only. Future API endpoints (roadmap items 4-6) will query these tables to provide geography name lookups for property data.

### External Services
- **ONS (Office for National Statistics):** Source of GSS codes and geography name data that will populate these tables via seeders (Task Group 6)

### Internal Dependencies
- Will be consumed by Property model (Task Group 5) to establish relationships between properties and their geographies
- Will be populated by lookup table seeders (Task Group 6) reading ONS CSV files
- Provides the foundation for future API endpoints that translate geography codes to human-readable names

## Known Issues & Limitations

### Issues
None identified during implementation.

### Limitations
1. **No Data Validation Beyond Database Constraints**
   - Description: Models rely solely on database-level constraints (NOT NULL, foreign keys) for validation
   - Reason: Specification defers application-level validation to seeder and import specifications
   - Future Consideration: If manual data entry is added, implement Laravel validation rules in model or request classes

2. **Welsh Names Only for LADs and Parishes**
   - Description: Only LocalAuthorityDistrict and Parish models include Welsh language name columns
   - Reason: ONS source data provides Welsh names only for these geography types
   - Future Consideration: If ONS extends Welsh naming to other geographies, migrations can add those columns

3. **No Geometry/Spatial Data**
   - Description: Tables store only codes and names, not boundary polygons
   - Reason: Boundary data is cached separately in boundary_caches table (Task Group 4) to avoid bloating lookup tables
   - Future Consideration: This separation is by design and improves query performance for name lookups

## Performance Considerations
Indexes on all foreign key columns ensure efficient JOIN operations when traversing geography hierarchies. Custom string primary keys (GSS codes) eliminate the need for additional unique indexes on code columns. Lookup tables are relatively small (largest is parishes at ~11,000 records) and will fit entirely in database memory for fast access. The use of CHAR data type for fixed-length GSS codes provides optimal storage and comparison performance.

## Security Considerations
Foreign key constraints prevent orphaned records and maintain referential integrity. Database-level constraints ensure data cannot be corrupted through application bugs. No sensitive data is stored in these lookup tables - all data is public ONS reference information.

## Dependencies for Other Tasks
This implementation satisfies dependencies for:
- **Task Group 3:** Properties Tables (migrations must exist before properties table can add foreign keys)
- **Task Group 5:** Eloquent Models (models created here will be extended with relationships in Task Group 5)
- **Task Group 6:** Lookup Table Seeders (tables must exist before seeders can populate them)

## Notes
The migrations use timestamps in the format `2025_12_23_00000X` to ensure deterministic ordering. In a production environment with Laravel artisan, these would typically be generated with the current timestamp when running `php artisan make:migration`. For this implementation, fixed timestamps were used to guarantee the correct execution sequence matches the specification's dependency order.

All 8 models follow a consistent pattern: custom string primary key, no auto-increment, standard timestamps enabled, bidirectional relationships. This consistency makes the codebase easy to understand and maintain.

The test implementation uses Database Capsule Manager instead of Laravel's full testing infrastructure due to the minimal Laravel setup in this project. This approach still validates all critical model behaviors while keeping tests lightweight and fast.

All acceptance criteria from the task specification have been met:
- 8 tests written and passing
- All 8 lookup table migrations created in dependency order
- Foreign key relationships properly defined
- Indexes created on all foreign key columns
- Timestamps (created_at, updated_at) present on all lookup tables
- No soft delete columns (per specification)
