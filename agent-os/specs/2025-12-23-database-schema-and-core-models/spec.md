# Specification: Database Schema and Core Models

## Goal
Create the foundational PostgreSQL database schema for LocaleLogic, storing 41 million UPRN property records from the ONS UPRN Directory (ONSUD) with geography codes, pre-converted coordinates, and lookup tables for translating GSS codes to human-readable names. Includes boundary cache table and data version tracking to support automated 6-weekly imports.

## User Stories
- As a developer, I want to query property records by UPRN or geography codes so that I can build location-based API endpoints efficiently
- As a data administrator, I want zero-downtime table swaps during ONSUD imports so that API services remain available during 6-weekly data updates
- As an API consumer, I want geography codes translated to human-readable names so that responses are meaningful without additional lookups

## Specific Requirements

**Properties Table Structure**
- Create `properties` table as core data store for 41 million property records
- UPRN as BIGINT PRIMARY KEY (unique property reference number)
- Store postcode as pcds (VARCHAR(8) NOT NULL) normalized to uppercase with space
- Store OS Grid coordinates: gridgb1e (easting, INT NOT NULL), gridgb1n (northing, INT NOT NULL)
- Store WGS84 coordinates: lat (DECIMAL(9,6) NOT NULL), lng (DECIMAL(9,6) NOT NULL)
- Include geography code columns: wd25cd (ward), ced25cd (county electoral division), parncp25cd (parish), lad25cd (local authority district), pcon24cd (constituency), lsoa21cd, msoa21cd, rgn25cd (region), ruc21ind (rural/urban classification), pfa23cd (police force area)
- All geography code columns CHAR(9) except lad25cd which is NOT NULL, others nullable
- Use Laravel timestamps convention (no created_at/updated_at on properties table for performance)

**Properties Table Indexes**
- Create B-tree indexes on individual columns: idx_properties_pcds, idx_properties_wd25cd, idx_properties_ced25cd, idx_properties_parncp25cd, idx_properties_lad25cd, idx_properties_pcon24cd
- Create composite indexes for common query patterns: idx_properties_parish_postcode (parncp25cd, pcds), idx_properties_lad_postcode (lad25cd, pcds), idx_properties_ward_postcode (wd25cd, pcds)
- All indexes created after table population for optimal build performance

**Properties Staging Table**
- Create `properties_staging` table with identical structure to `properties` for zero-downtime imports
- Use TableSwapService to perform atomic table rename: staging becomes live, live becomes old, old gets dropped
- Enables validation of staging data before production swap and rollback capability

**Ward Lookup Table**
- Table: `wards` with wd25cd (CHAR(9) PRIMARY KEY), wd25nm (VARCHAR(100) NOT NULL), lad25cd (CHAR(9) NOT NULL)
- Store approximately 9,000 ward records from ONS lookup data
- Define relationship to properties (hasMany) and parent LAD (belongsTo)

**County Electoral Division Lookup Table**
- Table: `county_electoral_divisions` with ced25cd (CHAR(9) PRIMARY KEY), ced25nm (VARCHAR(100) NOT NULL), cty25cd (CHAR(9) NOT NULL)
- Store approximately 1,400 CED records from ONS lookup data
- Define relationship to properties (hasMany) and parent county (belongsTo)

**Parish Lookup Table**
- Table: `parishes` with parncp25cd (CHAR(9) PRIMARY KEY), parncp25nm (VARCHAR(100) NOT NULL), parncp25nmw (VARCHAR(100) for Welsh name), lad25cd (CHAR(9) NOT NULL)
- Store approximately 11,000 parish records from ONS lookup data
- Include Welsh language names where applicable
- Define relationship to properties (hasMany) and parent LAD (belongsTo)

**Local Authority District Lookup Table**
- Table: `local_authority_districts` with lad25cd (CHAR(9) PRIMARY KEY), lad25nm (VARCHAR(100) NOT NULL), lad25nmw (VARCHAR(100) for Welsh name), rgn25cd (CHAR(9) for region)
- Store approximately 350 LAD records from ONS lookup data
- Include Welsh language names where applicable
- Define relationship to properties, wards, parishes (hasMany) and parent region (belongsTo)

**Constituency Lookup Table**
- Table: `constituencies` with pcon24cd (CHAR(9) PRIMARY KEY), pcon24nm (VARCHAR(100) NOT NULL)
- Store approximately 650 Westminster constituency records from ONS lookup data
- Define relationship to properties (hasMany)

**Region Lookup Table**
- Table: `regions` with rgn25cd (CHAR(9) PRIMARY KEY), rgn25nm (VARCHAR(50) NOT NULL)
- Store 12 region records for England, Wales, Scotland
- Define relationship to properties and LADs (hasMany)

**County Lookup Table**
- Table: `counties` with cty25cd (CHAR(9) PRIMARY KEY), cty25nm (VARCHAR(100) NOT NULL)
- Store approximately 30 county records from ONS lookup data
- Define relationship to CEDs (hasMany)

**Police Force Area Lookup Table**
- Table: `police_force_areas` with pfa23cd (CHAR(9) PRIMARY KEY), pfa23nm (VARCHAR(100) NOT NULL)
- Store 44 police force area records from ONS lookup data
- Define relationship to properties (hasMany)

**Boundary Cache Table**
- Table: `boundary_caches` to store GeoJSON polygons fetched from ONS Open Geography Portal API
- Columns: id (SERIAL PRIMARY KEY), geography_type (VARCHAR(20) NOT NULL), geography_code (CHAR(9) NOT NULL), boundary_resolution (VARCHAR(10) DEFAULT 'BFC' NOT NULL), geojson (TEXT NOT NULL), fetched_at (TIMESTAMP NOT NULL), expires_at (TIMESTAMP), source_url (VARCHAR(500))
- UNIQUE constraint on (geography_type, geography_code, boundary_resolution) to prevent duplicates
- Supported geography types: ward, ced, parish, lad, constituency, county, pfa, region
- Always request BFC resolution (Full resolution, Clipped to coastline) for consistent boundaries
- Index on expires_at for efficient cache expiry queries

**Data Versions Table**
- Table: `data_versions` to track ONSUD import history and current version
- Columns: id (SERIAL PRIMARY KEY), dataset (VARCHAR(20) NOT NULL), epoch (INT NOT NULL), release_date (DATE NOT NULL), imported_at (TIMESTAMP NOT NULL), record_count (INT), file_hash (VARCHAR(64)), status (VARCHAR(20) DEFAULT 'current' NOT NULL), notes (TEXT)
- UNIQUE constraint on (dataset, epoch) to prevent duplicate version entries
- Status values: importing, current, archived, failed
- Enables version tracking for 6-weekly ONSUD updates and rollback identification

**Laravel Eloquent Models**
- Property model with primary key `uprn`, no timestamps, fillable fields for all geography codes and coordinates
- Define relationships to Ward, CountyElectoralDivision, Parish, LocalAuthorityDistrict, Constituency, Region, PoliceForceArea using belongsTo based on respective code columns
- Ward, CountyElectoralDivision, Parish, LocalAuthorityDistrict, Constituency, Region, County, PoliceForceArea models with standard timestamps
- Each lookup model defines hasMany relationship back to Property
- BoundaryCache model with casts for fetched_at and expires_at as datetime
- DataVersion model with casts for release_date as date, imported_at as datetime

**Coordinate Conversion Service**
- CoordinateConverter service class using proj4php library for coordinate transformations
- Method osGridToWgs84(easting, northing) converts single coordinate pair from EPSG:27700 (British National Grid) to EPSG:4326 (WGS84 lat/lng)
- Method batchConvert(coordinates) for optimized bulk conversions during ONSUD import to minimize overhead
- Returns array with lat and lng keys for integration with property records

**Table Swap Service**
- TableSwapService class to orchestrate zero-downtime table replacement
- Method swapPropertiesTable() performs atomic operation: ALTER TABLE to rename properties_staging to properties_new, properties to properties_old, properties_new to properties
- Method validates staging table has expected record count before swap
- Method provides rollback capability by reversing rename operations if validation fails
- After successful swap, DROP old properties_old table to free disk space

**Database Seeders for Lookup Tables**
- WardSeeder to populate wards table from ONS ward names CSV (~9,000 records)
- CedSeeder to populate county_electoral_divisions from ONS CED names CSV (~1,400 records)
- ParishSeeder to populate parishes from ONS parish names CSV with English and Welsh names (~11,000 records)
- LadSeeder to populate local_authority_districts from ONS LAD names CSV with English and Welsh names (~350 records)
- ConstituencySeeder to populate constituencies from ONS constituency names CSV (~650 records)
- RegionSeeder to populate regions from ONS region names CSV (12 records)
- CountySeeder to populate counties from ONS county names CSV (~30 records)
- PfaSeeder to populate police_force_areas from ONS PFA names CSV (44 records)

**Migration File Organization**
- Create migrations in dependency order: lookup tables first, then properties table, then staging table, then boundary cache and data versions
- Migration 001: create_regions_table
- Migration 002: create_counties_table
- Migration 003: create_local_authority_districts_table
- Migration 004: create_wards_table
- Migration 005: create_county_electoral_divisions_table
- Migration 006: create_parishes_table
- Migration 007: create_constituencies_table
- Migration 008: create_police_force_areas_table
- Migration 009: create_properties_table (with foreign key references to lookup tables)
- Migration 010: create_properties_staging_table (identical to properties)
- Migration 011: create_boundary_caches_table
- Migration 012: create_data_versions_table

## Visual Design

No visual assets provided for this database schema specification.

## Existing Code to Leverage

**Laravel Migration Patterns**
- Use Schema::create() with callback for table definition following Laravel conventions
- Use $table->bigInteger('uprn')->primary() for UPRN primary key
- Use $table->char('code', 9) for fixed-length GSS codes
- Use $table->decimal('lat', 9, 6) for coordinate precision
- Use $table->index() and $table->unique() for constraint definitions
- Use $table->foreign()->references()->on() for relationship constraints if enforced at database level

**Laravel Model Patterns**
- Extend Illuminate\Database\Eloquent\Model for all models
- Define $table property for non-standard table names (e.g., properties instead of properties)
- Define $primaryKey property for non-standard primary keys (e.g., uprn instead of id)
- Define $fillable array for mass assignment protection
- Use $timestamps = false on Property model to disable timestamp columns
- Use belongsTo() and hasMany() relationship methods with foreign key parameters

**Laravel Seeder Patterns**
- Extend Illuminate\Database\Seeder for all seeder classes
- Use DB::table()->insert() for bulk inserts or Model::create() for smaller datasets
- Use Storage::disk()->get() to read CSV files from storage/app directory
- Use str_getcsv() or League\Csv for CSV parsing
- Call seeders from DatabaseSeeder in logical dependency order

**PHP Service Class Patterns**
- Create service classes in app/Services directory
- Use dependency injection for database connections and external libraries
- Return associative arrays or DTOs from service methods for flexibility
- Throw descriptive exceptions on failures with context for debugging
- Use DB::transaction() for multi-step database operations requiring atomicity

**Proj4php Library Integration**
- Install via composer: proj4php/proj4php
- Initialize proj4 object with EPSG:27700 (OS Grid) and EPSG:4326 (WGS84) definitions
- Use transform() method to convert coordinates between projections
- Cache projection definitions to avoid repeated initialization overhead

## Out of Scope
- Full postal address storage (requires separate AddressBase license not available)
- ONSPD postcodes lookup table (deferred to separate specification)
- API authentication tables for API keys and usage logging (deferred to separate specification)
- Actual ONSUD data import scripts or commands (covered in roadmap item 3)
- Automated cleanup jobs for terminated postcodes (not applicable to ONSUD dataset)
- API endpoints using these database tables (covered in roadmap items 4-6)
- Rate limiting middleware or logic (covered in roadmap item 10)
- Redis cache schema or configuration (covered in roadmap item 7)
- Spatial geometry columns or PostGIS extensions (boundaries cached as GeoJSON text)
- User authentication or authorization system (API-only service with separate auth spec)
- Soft delete functionality (not required for geography reference data)
- Output Area (OA), Built-up Area, LSOA/MSOA name lookups (codes stored but names not included in initial scope)
- Automated 6-weekly ONSUD update scheduling (import process covered separately)
- Data validation rules beyond database constraints (covered in import specification)
- Frontend UI for browsing or managing database records (API-only service)
