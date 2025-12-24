# Task Breakdown: Database Schema and Core Models

## Overview
Total Tasks: 60 tasks organized into 6 strategic groups
Focus: PostgreSQL schema for 41M UPRN properties, 8 geography lookup tables, coordinate conversion, zero-downtime table swaps, and comprehensive indexing

## Task List

### Phase 1: Foundation Layer

#### Task Group 1: Dependencies and Service Infrastructure
**Dependencies:** None

- [x] 1.0 Complete foundation layer setup
  - [x] 1.1 Write 2-8 focused tests for CoordinateConverter service
    - Limit to 2-8 highly focused tests maximum
    - Test only critical conversion behaviors (e.g., valid OS Grid to WGS84 conversion, batch conversion returns correct structure, invalid input handling)
    - Skip exhaustive testing of all edge cases and coordinate variations
  - [x] 1.2 Install proj4php library via Composer
    - Run: composer require proj4php/proj4php
    - Verify installation in composer.json
  - [x] 1.3 Create CoordinateConverter service class
    - Location: app/Services/CoordinateConverter.php
    - Initialize with EPSG:27700 (British National Grid) and EPSG:4326 (WGS84) projection definitions
    - Cache projection definitions to avoid repeated initialization
  - [x] 1.4 Implement osGridToWgs84() method
    - Parameters: easting (int), northing (int)
    - Return: array with 'lat' and 'lng' keys
    - Use proj4php transform() method for conversion
    - Precision: lat/lng as decimal(9,6)
  - [x] 1.5 Implement batchConvert() method
    - Parameter: array of coordinate pairs
    - Return: array of results with lat/lng
    - Optimize for bulk conversions during ONSUD import
    - Minimize overhead by reusing projection object
  - [x] 1.6 Add error handling with descriptive exceptions
    - Throw exceptions for invalid coordinate ranges
    - Include context (easting/northing values) in error messages
    - Handle proj4php library errors gracefully
  - [x] 1.7 Ensure CoordinateConverter tests pass
    - Run ONLY the 2-8 tests written in 1.1
    - Verify conversion accuracy (compare to known coordinates)
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 1.1 pass
- proj4php library installed and accessible
- Single coordinate conversion works correctly (EPSG:27700 to EPSG:4326)
- Batch conversion processes multiple coordinates efficiently
- Error handling provides clear messages for invalid input

---

### Phase 2: Database Schema - Lookup Tables

#### Task Group 2: Geography Lookup Tables and Migrations
**Dependencies:** Task Group 1

- [x] 2.0 Complete geography lookup tables layer
  - [x] 2.1 Write 2-8 focused tests for lookup table models
    - Limit to 2-8 highly focused tests maximum
    - Test only critical model behaviors (e.g., Region model retrieval, LAD relationship to Region, Ward relationship to LAD)
    - Skip exhaustive testing of all relationships and validations
  - [x] 2.2 Create migration 001: create_regions_table
    - Columns: rgn25cd CHAR(9) PRIMARY KEY, rgn25nm VARCHAR(50) NOT NULL
    - Add standard timestamps: created_at, updated_at
    - No soft deletes (per spec: out of scope)
    - Migration filename: YYYY_MM_DD_HHMMSS_create_regions_table.php
  - [x] 2.3 Create migration 002: create_counties_table
    - Columns: cty25cd CHAR(9) PRIMARY KEY, cty25nm VARCHAR(100) NOT NULL
    - Add standard timestamps
    - Migration dependency: runs after regions
  - [x] 2.4 Create migration 003: create_local_authority_districts_table
    - Columns: lad25cd CHAR(9) PRIMARY KEY, lad25nm VARCHAR(100) NOT NULL, lad25nmw VARCHAR(100) nullable, rgn25cd CHAR(9) nullable
    - Foreign key: rgn25cd REFERENCES regions(rgn25cd)
    - Add index on rgn25cd for relationship queries
    - Add standard timestamps
  - [x] 2.5 Create migration 004: create_wards_table
    - Columns: wd25cd CHAR(9) PRIMARY KEY, wd25nm VARCHAR(100) NOT NULL, lad25cd CHAR(9) NOT NULL
    - Foreign key: lad25cd REFERENCES local_authority_districts(lad25cd)
    - Add index on lad25cd
    - Add standard timestamps
  - [x] 2.6 Create migration 005: create_county_electoral_divisions_table
    - Columns: ced25cd CHAR(9) PRIMARY KEY, ced25nm VARCHAR(100) NOT NULL, cty25cd CHAR(9) NOT NULL
    - Foreign key: cty25cd REFERENCES counties(cty25cd)
    - Add index on cty25cd
    - Add standard timestamps
  - [x] 2.7 Create migration 006: create_parishes_table
    - Columns: parncp25cd CHAR(9) PRIMARY KEY, parncp25nm VARCHAR(100) NOT NULL, parncp25nmw VARCHAR(100) nullable, lad25cd CHAR(9) NOT NULL
    - Foreign key: lad25cd REFERENCES local_authority_districts(lad25cd)
    - Add index on lad25cd
    - Add standard timestamps
    - Include Welsh language names column
  - [x] 2.8 Create migration 007: create_constituencies_table
    - Columns: pcon24cd CHAR(9) PRIMARY KEY, pcon24nm VARCHAR(100) NOT NULL
    - Add standard timestamps
    - ~650 Westminster constituencies
  - [x] 2.9 Create migration 008: create_police_force_areas_table
    - Columns: pfa23cd CHAR(9) PRIMARY KEY, pfa23nm VARCHAR(100) NOT NULL
    - Add standard timestamps
    - 44 police force areas
  - [x] 2.10 Run migrations and verify table creation
    - Execute: php artisan migrate
    - Verify all 8 lookup tables created successfully
    - Check foreign key constraints are in place
    - Verify indexes created on foreign key columns
  - [x] 2.11 Ensure lookup table tests pass
    - Run ONLY the 2-8 tests written in 2.1
    - Verify table structures match migrations
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 2.1 pass
- All 8 lookup table migrations created in dependency order
- Migrations run successfully without errors
- Foreign key relationships properly defined
- Indexes created on all foreign key columns
- Timestamps (created_at, updated_at) present on all lookup tables
- No soft delete columns (per spec)

---

### Phase 3: Database Schema - Core Properties Tables

#### Task Group 3: Properties Tables and Indexing Strategy
**Dependencies:** Task Group 2 (lookup tables must exist for foreign keys)

- [x] 3.0 Complete properties tables layer
  - [x] 3.1 Write 2-8 focused tests for Property model
    - Limit to 2-8 highly focused tests maximum
    - Test only critical behaviors (e.g., retrieve property by UPRN, Property belongs to Ward relationship, coordinate fields populated)
    - Skip exhaustive testing of all relationships and field validations
  - [x] 3.2 Create migration 009: create_properties_table
    - Primary key: uprn BIGINT (not auto-incrementing ID)
    - Postcode: pcds VARCHAR(8) NOT NULL (normalized uppercase with space)
    - OS Grid coordinates: gridgb1e INT NOT NULL, gridgb1n INT NOT NULL
    - WGS84 coordinates: lat DECIMAL(9,6) NOT NULL, lng DECIMAL(9,6) NOT NULL
    - Geography codes (all CHAR(9), nullable except lad25cd NOT NULL):
      - wd25cd (ward)
      - ced25cd (county electoral division)
      - parncp25cd (parish)
      - lad25cd (local authority district) - NOT NULL
      - pcon24cd (constituency)
      - lsoa21cd (LSOA)
      - msoa21cd (MSOA)
      - rgn25cd (region)
      - ruc21ind (rural/urban classification)
      - pfa23cd (police force area)
    - NO timestamps (per spec: performance optimization for 41M rows)
    - NO soft deletes (per spec: out of scope)
  - [x] 3.3 Add foreign key constraints to properties table
    - Optional: foreign keys to lookup tables (wd25cd, ced25cd, parncp25cd, lad25cd, pcon24cd, rgn25cd, pfa23cd)
    - Consider performance impact on 41M row table
    - Use ON DELETE SET NULL for nullable geography codes
    - Use appropriate cascade behavior per Laravel standards
  - [x] 3.4 Create individual B-tree indexes (deferred until after data population)
    - Document indexes to create: idx_properties_pcds, idx_properties_wd25cd, idx_properties_ced25cd, idx_properties_parncp25cd, idx_properties_lad25cd, idx_properties_pcon24cd
    - Note: Index creation deferred to separate migration run AFTER bulk data import for optimal build performance
  - [x] 3.5 Create composite indexes (deferred until after data population)
    - Document composite indexes:
      - idx_properties_parish_postcode (parncp25cd, pcds)
      - idx_properties_lad_postcode (lad25cd, pcds)
      - idx_properties_ward_postcode (wd25cd, pcds)
    - Note: Supports common query patterns like "all properties in ward X with postcode Y"
  - [x] 3.6 Create migration 010: create_properties_staging_table
    - Identical structure to properties table
    - Same columns, same data types, same constraints
    - Purpose: zero-downtime imports via table swap
    - No indexes initially (added after data load)
  - [x] 3.7 Run properties table migrations
    - Execute: php artisan migrate
    - Verify properties and properties_staging tables created
    - Confirm UPRN is primary key (not default 'id')
    - Verify NO created_at/updated_at columns exist
  - [x] 3.8 Ensure Property model tests pass
    - Run ONLY the 2-8 tests written in 3.1
    - Verify UPRN primary key works correctly
    - Verify coordinate fields accept correct data types
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 3.1 pass
- Properties table created with UPRN as BIGINT primary key
- All geography code columns present and correctly typed
- Both OS Grid (gridgb1e, gridgb1n) and WGS84 (lat, lng) coordinate fields present
- NO timestamp columns on properties table
- Properties_staging table has identical structure to properties
- Foreign key constraints defined (if implemented)
- Index strategy documented for post-import creation

---

### Phase 4: Database Schema - Supporting Tables

#### Task Group 4: Boundary Cache and Data Version Tracking
**Dependencies:** Task Group 3

- [x] 4.0 Complete supporting tables layer
  - [x] 4.1 Write 2-8 focused tests for BoundaryCache and DataVersion models
    - Limit to 2-8 highly focused tests maximum
    - Test only critical behaviors (e.g., BoundaryCache unique constraint on geography_type+code+resolution, DataVersion status values)
    - Skip exhaustive testing of all fields and validations
  - [x] 4.2 Create migration 011: create_boundary_caches_table
    - Columns:
      - id SERIAL PRIMARY KEY (auto-incrementing)
      - geography_type VARCHAR(20) NOT NULL (ward, ced, parish, lad, constituency, county, pfa, region)
      - geography_code CHAR(9) NOT NULL (GSS code)
      - boundary_resolution VARCHAR(10) DEFAULT 'BFC' NOT NULL (Full resolution, Clipped to coastline)
      - geojson TEXT NOT NULL (GeoJSON polygon from ONS Open Geography Portal)
      - fetched_at TIMESTAMP NOT NULL
      - expires_at TIMESTAMP nullable
      - source_url VARCHAR(500) nullable
    - Add standard timestamps (created_at, updated_at)
    - UNIQUE constraint on (geography_type, geography_code, boundary_resolution)
    - Index on expires_at for cache expiry queries
  - [x] 4.3 Create migration 012: create_data_versions_table
    - Columns:
      - id SERIAL PRIMARY KEY
      - dataset VARCHAR(20) NOT NULL (e.g., 'ONSUD', 'Ward Lookup')
      - epoch INT NOT NULL (version number)
      - release_date DATE NOT NULL
      - imported_at TIMESTAMP NOT NULL
      - record_count INT nullable
      - file_hash VARCHAR(64) nullable (SHA-256 hash for verification)
      - status VARCHAR(20) DEFAULT 'current' NOT NULL (importing, current, archived, failed)
      - notes TEXT nullable
    - Add standard timestamps (created_at, updated_at)
    - UNIQUE constraint on (dataset, epoch)
    - Enables tracking of 6-weekly ONSUD updates
  - [x] 4.4 Run supporting table migrations
    - Execute: php artisan migrate
    - Verify boundary_caches table created with unique constraint
    - Verify data_versions table created with unique constraint
    - Check indexes on expires_at created
  - [x] 4.5 Ensure supporting table tests pass
    - Run ONLY the 2-8 tests written in 4.1
    - Verify unique constraints prevent duplicates
    - Verify status field accepts expected values
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 4.1 pass
- Boundary_caches table stores GeoJSON as TEXT
- Unique constraint prevents duplicate boundary entries
- Data_versions table tracks import history with epoch and status
- Unique constraint prevents duplicate dataset version entries
- Timestamps present on both tables
- Index on expires_at for cache management

---

### Phase 5: Laravel Models and Relationships

#### Task Group 5: Eloquent Models with Relationships
**Dependencies:** Task Group 4 (all tables must exist)

- [ ] 5.0 Complete Laravel Eloquent models layer
  - [ ] 5.1 Write 2-8 focused tests for model relationships
    - Limit to 2-8 highly focused tests maximum
    - Test only critical relationship behaviors (e.g., Property->ward() returns Ward instance, Ward->properties() returns collection, LAD->wards() hasMany works)
    - Skip exhaustive testing of all relationship permutations
  - [ ] 5.2 Create Region model
    - Location: app/Models/Region.php
    - Table: regions
    - Primary key: rgn25cd (not default 'id')
    - Fillable: rgn25cd, rgn25nm
    - Timestamps: true (created_at, updated_at)
    - Relationships:
      - hasMany(Property::class, 'rgn25cd', 'rgn25cd')
      - hasMany(LocalAuthorityDistrict::class, 'rgn25cd', 'rgn25cd')
  - [ ] 5.3 Create County model
    - Location: app/Models/County.php
    - Table: counties
    - Primary key: cty25cd
    - Fillable: cty25cd, cty25nm
    - Timestamps: true
    - Relationships:
      - hasMany(CountyElectoralDivision::class, 'cty25cd', 'cty25cd')
  - [ ] 5.4 Create LocalAuthorityDistrict model
    - Location: app/Models/LocalAuthorityDistrict.php
    - Table: local_authority_districts
    - Primary key: lad25cd
    - Fillable: lad25cd, lad25nm, lad25nmw, rgn25cd
    - Timestamps: true
    - Relationships:
      - belongsTo(Region::class, 'rgn25cd', 'rgn25cd')
      - hasMany(Property::class, 'lad25cd', 'lad25cd')
      - hasMany(Ward::class, 'lad25cd', 'lad25cd')
      - hasMany(Parish::class, 'lad25cd', 'lad25cd')
  - [ ] 5.5 Create Ward model
    - Location: app/Models/Ward.php
    - Table: wards
    - Primary key: wd25cd
    - Fillable: wd25cd, wd25nm, lad25cd
    - Timestamps: true
    - Relationships:
      - belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd')
      - hasMany(Property::class, 'wd25cd', 'wd25cd')
  - [ ] 5.6 Create CountyElectoralDivision model
    - Location: app/Models/CountyElectoralDivision.php
    - Table: county_electoral_divisions
    - Primary key: ced25cd
    - Fillable: ced25cd, ced25nm, cty25cd
    - Timestamps: true
    - Relationships:
      - belongsTo(County::class, 'cty25cd', 'cty25cd')
      - hasMany(Property::class, 'ced25cd', 'ced25cd')
  - [ ] 5.7 Create Parish model
    - Location: app/Models/Parish.php
    - Table: parishes
    - Primary key: parncp25cd
    - Fillable: parncp25cd, parncp25nm, parncp25nmw, lad25cd
    - Timestamps: true
    - Relationships:
      - belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd')
      - hasMany(Property::class, 'parncp25cd', 'parncp25cd')
  - [ ] 5.8 Create Constituency model
    - Location: app/Models/Constituency.php
    - Table: constituencies
    - Primary key: pcon24cd
    - Fillable: pcon24cd, pcon24nm
    - Timestamps: true
    - Relationships:
      - hasMany(Property::class, 'pcon24cd', 'pcon24cd')
  - [ ] 5.9 Create PoliceForceArea model
    - Location: app/Models/PoliceForceArea.php
    - Table: police_force_areas
    - Primary key: pfa23cd
    - Fillable: pfa23cd, pfa23nm
    - Timestamps: true
    - Relationships:
      - hasMany(Property::class, 'pfa23cd', 'pfa23cd')
  - [ ] 5.10 Create Property model
    - Location: app/Models/Property.php
    - Table: properties
    - Primary key: uprn (not default 'id')
    - Increment: false (UPRN is assigned by ONS, not auto-increment)
    - Fillable: uprn, pcds, gridgb1e, gridgb1n, lat, lng, wd25cd, ced25cd, parncp25cd, lad25cd, pcon24cd, lsoa21cd, msoa21cd, rgn25cd, ruc21ind, pfa23cd
    - Timestamps: false (NO created_at/updated_at per spec)
    - Relationships:
      - belongsTo(Ward::class, 'wd25cd', 'wd25cd')
      - belongsTo(CountyElectoralDivision::class, 'ced25cd', 'ced25cd')
      - belongsTo(Parish::class, 'parncp25cd', 'parncp25cd')
      - belongsTo(LocalAuthorityDistrict::class, 'lad25cd', 'lad25cd')
      - belongsTo(Constituency::class, 'pcon24cd', 'pcon24cd')
      - belongsTo(Region::class, 'rgn25cd', 'rgn25cd')
      - belongsTo(PoliceForceArea::class, 'pfa23cd', 'pfa23cd')
  - [ ] 5.11 Create BoundaryCache model
    - Location: app/Models/BoundaryCache.php
    - Table: boundary_caches
    - Primary key: id (default auto-increment)
    - Fillable: geography_type, geography_code, boundary_resolution, geojson, fetched_at, expires_at, source_url
    - Timestamps: true
    - Casts:
      - fetched_at => 'datetime'
      - expires_at => 'datetime'
  - [ ] 5.12 Create DataVersion model
    - Location: app/Models/DataVersion.php
    - Table: data_versions
    - Primary key: id
    - Fillable: dataset, epoch, release_date, imported_at, record_count, file_hash, status, notes
    - Timestamps: true
    - Casts:
      - release_date => 'date'
      - imported_at => 'datetime'
  - [ ] 5.13 Ensure model relationship tests pass
    - Run ONLY the 2-8 tests written in 5.1
    - Verify belongsTo relationships return correct model instances
    - Verify hasMany relationships return collections
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 5.1 pass
- All 11 Eloquent models created (8 lookup + Property + BoundaryCache + DataVersion)
- Property model uses 'uprn' as primary key with timestamps disabled
- All lookup models use GSS code columns as primary keys
- Relationships defined bidirectionally (belongsTo and hasMany)
- Foreign key columns specified explicitly in relationship definitions
- BoundaryCache and DataVersion models have appropriate datetime casts

---

### Phase 6: Data Seeders and Services

#### Task Group 6: Lookup Table Seeders and Table Swap Service
**Dependencies:** Task Group 5 (models must exist)

- [x] 6.0 Complete seeders and services layer
  - [x] 6.1 Write 2-8 focused tests for TableSwapService
    - Limit to 2-8 highly focused tests maximum
    - Test only critical swap behaviors (e.g., successful table swap renames correctly, validation check before swap, rollback on validation failure)
    - Skip exhaustive testing of all edge cases and failure scenarios
  - [x] 6.2 Create TableSwapService class
    - Location: app/Services/TableSwapService.php
    - Purpose: Zero-downtime table replacement for ONSUD imports
    - Use DB::transaction() for atomic operations
  - [x] 6.3 Implement validateStagingTable() method
    - Check properties_staging has expected record count
    - Verify required columns populated (uprn, lad25cd, coordinates)
    - Return validation result with details
  - [x] 6.4 Implement swapPropertiesTable() method
    - Atomic operation using raw SQL ALTER TABLE RENAME
    - Step 1: Rename properties_staging to properties_new
    - Step 2: Rename properties to properties_old
    - Step 3: Rename properties_new to properties
    - Wrap in DB::transaction() for rollback capability
    - Validate staging before swap
  - [x] 6.5 Implement rollbackSwap() method
    - Reverse rename operations if validation fails
    - Restore properties_old to properties
    - Provide clear error messages
  - [x] 6.6 Implement dropOldTable() method
    - DROP properties_old table after successful swap
    - Free disk space (41M row table is large)
    - Only execute after confirming new table is stable
  - [x] 6.7 Create RegionSeeder
    - Location: database/seeders/RegionSeeder.php
    - Read ONS region names CSV from storage/app/data/regions.csv
    - Parse CSV with str_getcsv() or League\Csv
    - Insert ~12 region records into regions table
    - Use DB::table('regions')->insert() for bulk insert
  - [x] 6.8 Create CountySeeder
    - Location: database/seeders/CountySeeder.php
    - Read counties CSV from storage/app/data/counties.csv
    - Insert ~30 county records
  - [x] 6.9 Create LadSeeder
    - Location: database/seeders/LadSeeder.php
    - Read LAD names CSV from storage/app/data/lads.csv
    - Parse English and Welsh names (lad25nm, lad25nmw)
    - Insert ~350 LAD records
  - [x] 6.10 Create WardSeeder
    - Location: database/seeders/WardSeeder.php
    - Read ward names CSV from storage/app/data/wards.csv
    - Insert ~9,000 ward records
    - Link to parent LAD via lad25cd
  - [x] 6.11 Create CedSeeder
    - Location: database/seeders/CedSeeder.php
    - Read CED names CSV from storage/app/data/ceds.csv
    - Insert ~1,400 CED records
    - Link to parent county via cty25cd
  - [x] 6.12 Create ParishSeeder
    - Location: database/seeders/ParishSeeder.php
    - Read parish names CSV from storage/app/data/parishes.csv
    - Parse English and Welsh names (parncp25nm, parncp25nmw)
    - Insert ~11,000 parish records
  - [x] 6.13 Create ConstituencySeeder
    - Location: database/seeders/ConstituencySeeder.php
    - Read constituency names CSV from storage/app/data/constituencies.csv
    - Insert ~650 Westminster constituency records
  - [x] 6.14 Create PfaSeeder
    - Location: database/seeders/PfaSeeder.php
    - Read PFA names CSV from storage/app/data/pfas.csv
    - Insert 44 police force area records
  - [x] 6.15 Update DatabaseSeeder to call all lookup seeders
    - Location: database/seeders/DatabaseSeeder.php
    - Call seeders in dependency order:
      1. RegionSeeder
      2. CountySeeder
      3. LadSeeder
      4. WardSeeder
      5. CedSeeder
      6. ParishSeeder
      7. ConstituencySeeder
      8. PfaSeeder
  - [x] 6.16 Test seeder execution (if CSV data available)
    - Run: php artisan db:seed
    - Verify lookup tables populated
    - Check record counts match expected (~12 regions, ~30 counties, ~350 LADs, etc.)
    - Note: This task may be deferred if CSV files not yet available
  - [x] 6.17 Ensure TableSwapService tests pass
    - Run ONLY the 2-8 tests written in 6.1
    - Verify validation prevents invalid swaps
    - Verify rollback works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 6.1 pass
- TableSwapService implements zero-downtime swap logic
- Validation checks staging table before swap
- Atomic transaction ensures rollback on failure
- All 8 lookup table seeders created
- Seeders read from CSV files in storage/app/data
- DatabaseSeeder calls all seeders in correct dependency order
- Seeders handle Welsh language names where applicable

---

## Execution Order

Recommended implementation sequence:

1. **Phase 1: Foundation Layer** (Task Group 1)
   - Install proj4php and create CoordinateConverter service
   - Enables coordinate conversion for property data import

2. **Phase 2: Database Schema - Lookup Tables** (Task Group 2)
   - Create all 8 geography lookup table migrations in dependency order
   - Must run before properties table due to foreign key references

3. **Phase 3: Database Schema - Core Properties Tables** (Task Group 3)
   - Create properties and properties_staging tables
   - Document indexing strategy (indexes created post-import)

4. **Phase 4: Database Schema - Supporting Tables** (Task Group 4)
   - Create boundary_caches and data_versions tables
   - Independent of other tables, can run after properties

5. **Phase 5: Laravel Models and Relationships** (Task Group 5)
   - Create all 11 Eloquent models
   - Requires all tables to exist first

6. **Phase 6: Data Seeders and Services** (Task Group 6)
   - Create TableSwapService for zero-downtime imports
   - Create all 8 lookup table seeders
   - Requires models to exist

---

## Testing Strategy

**Test Writing Philosophy:**
- Each task group starts with writing 2-8 focused tests maximum
- Tests cover ONLY critical behaviors, not exhaustive scenarios
- Each task group ends with running ONLY the tests written in that group
- NO full test suite execution until final verification phase
- Skip edge cases, performance tests, and non-critical validations during development

**Test Verification Schedule:**
- Task 1.7: Run CoordinateConverter tests only (2-8 tests)
- Task 2.11: Run lookup table model tests only (2-8 tests)
- Task 3.8: Run Property model tests only (2-8 tests)
- Task 4.5: Run BoundaryCache and DataVersion model tests only (2-8 tests)
- Task 5.13: Run model relationship tests only (2-8 tests)
- Task 6.17: Run TableSwapService tests only (2-8 tests)

**Post-Implementation Test Review:**
- Total expected tests: approximately 12-48 tests across all groups
- No dedicated test gap analysis group (tests written inline with development)
- Testing focuses exclusively on this spec's feature requirements
- Integration testing deferred to ONSUD import specification

---

## Notes

**Important Constraints:**
- NO soft delete functionality (per spec: out of scope)
- NO created_at/updated_at on properties table (performance optimization)
- NO ONSPD postcodes table (deferred to separate spec)
- NO API authentication tables (deferred to separate spec)
- Indexes on properties table created AFTER bulk data import for optimal performance

**Migration Dependency Order:**
Critical that migrations run in this exact sequence:
1. Regions (no dependencies)
2. Counties (no dependencies)
3. LADs (depends on Regions)
4. Wards (depends on LADs)
5. CEDs (depends on Counties)
6. Parishes (depends on LADs)
7. Constituencies (no dependencies)
8. Police Force Areas (no dependencies)
9. Properties (depends on all lookup tables if foreign keys used)
10. Properties Staging (identical to Properties)
11. Boundary Caches (no dependencies)
12. Data Versions (no dependencies)

**CSV Data Files:**
Seeders expect CSV files in storage/app/data/ directory:
- regions.csv (~12 records)
- counties.csv (~30 records)
- lads.csv (~350 records)
- wards.csv (~9,000 records)
- ceds.csv (~1,400 records)
- parishes.csv (~11,000 records)
- constituencies.csv (~650 records)
- pfas.csv (44 records)

**Future Dependencies:**
This schema supports upcoming roadmap items:
- Item 3: ONSUD Data Import Service (will use properties_staging and TableSwapService)
- Items 4-6: API endpoints (will query properties and lookup tables)
- Item 7: Boundary GeoJSON Cache (will use boundary_caches table)

**Standards Compliance:**
Tasks align with project standards:
- Laravel migration patterns with reversible up/down methods
- Eloquent model conventions with explicit primary keys and relationships
- PSR-12 PHP coding standards
- Database constraints enforced at database level
- Clear naming conventions for tables, columns, and models
- Minimal testing during development, focused on critical behaviors only
