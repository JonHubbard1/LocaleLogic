# Spec Requirements: Database Schema and Core Models

## Initial Description

Database Schema and Core Models - Create PostgreSQL database schema for:
- ONSUD properties table with UPRN, coordinates, geography codes
- ONSPD postcodes table
- ONS geography names lookup
- API authentication tables with indexes

This is the foundational database layer for the LocaleLogic UK geography microservice.

This is item #1 from the product roadmap:

> Database Schema and Core Models — Create PostgreSQL database schema for ONSUD (properties table with UPRN, coordinates, geography codes), ONSPD (postcodes table), ONS geography names lookup, and API authentication tables (API keys, usage logs). Include indexes for efficient postcode/UPRN/geography code queries. `M`

## Requirements Discussion

### First Round Questions

**Q1:** ONSUD Properties Table Structure - I assume the table should include columns for UPRN (bigint primary key), eastings/northings (integers for OS Grid coordinates), latitude/longitude (decimal for WGS84), and multiple geography code columns (ward, division, parish, constituency, LSOA, MSOA, etc. as varchar/text). Should we store both the raw OS Grid coordinates AND the converted WGS84 coordinates in the database, or calculate WGS84 on-the-fly?

**Answer:** Store BOTH raw OS Grid coordinates (eastings/northings) AND converted WGS84 coordinates (latitude/longitude) in the database.

**Q2:** Geography Code Columns - I'm thinking the ONSUD table should have separate columns for each geography type (e.g., ward_code, division_code, parish_code, constituency_code, lsoa_code, msoa_code, county_code, region_code). Should we also include any other ONS geography codes like OA (Output Area), Built-up Area, or Rural/Urban classification?

**Answer:** Yes, include ALL available ONS geography codes (ward, division, parish, constituency, LSOA, MSOA, OA, Built-up Area, Rural/Urban classification, etc.) even if not immediately used.

**Q3:** ONSPD Postcodes Table - I assume this should include postcode (varchar primary key), centroid coordinates (latitude/longitude as decimal), geography codes (similar to ONSUD), termination date (nullable datetime for terminated postcodes), and quality indicators. Should we normalize postcodes to a standard format (e.g., uppercase with space) or store as-provided?

**Answer:** Yes, normalize postcodes to standard format (uppercase with space).

**Q4:** ONS Geography Names Lookup - I'm thinking we need separate lookup tables for each geography type (e.g., wards, parishes, divisions, constituencies) with columns for GSS code (primary key) and human-readable name. Should these be separate tables (wards_lookup, parishes_lookup, etc.) or a single polymorphic table with a type column?

**Answer:** Use best practice approach - choose whatever will create the easiest/best coding solution.

**Q5:** API Authentication Tables - I assume we need an api_keys table with columns for key (hashed string, unique), name/description, rate_limit_tier, is_active (boolean), created_at, updated_at, and last_used_at. Should we also track the IP address that generated the key or any user/organization metadata?

**Answer:** Yes, track IP address and user/organization metadata.

**Q6:** Usage Logs Table - I'm thinking we need columns for endpoint (varchar), request_timestamp, response_time_ms, http_status_code, api_key_id (nullable foreign key), ip_address, user_agent, and error_message (nullable). Should we log every single request or implement sampling for high-volume endpoints?

**Answer:** Log every single request (no sampling).

**Q7:** Staging Tables Strategy - For zero-downtime updates, should we create parallel staging tables (e.g., onsud_properties_staging, onspd_postcodes_staging) that get swapped with production via table renaming, or use a different versioning strategy?

**Answer:** Use best long-term solution (not just easiest for now) - choose the best practice for zero-downtime updates.

**Q8:** Indexes Priority - I assume we need B-tree indexes on postcode, UPRN, and all geography code columns for fast lookups. Should we also create composite indexes for common query patterns (e.g., ward_code + postcode) or start minimal and add based on actual query patterns?

**Answer:** Create composite indexes for common query patterns (e.g., ward_code + postcode) from the start.

**Q9:** Data Retention for Terminated Postcodes - Should we keep historical records of terminated postcodes indefinitely with a termination_date, or archive/delete them after a retention period?

**Answer:** Keep terminated postcodes for 12 months with termination_date, then delete them.

**Q10:** Soft Deletes or Hard Deletes - Should the schema support soft deletes (deleted_at column) for any tables, or use hard deletes throughout?

**Answer:** Use soft deletes (deleted_at column) throughout the schema.

**Q11:** What should we explicitly NOT include? For example, should we avoid storing full postal addresses, boundary polygon geometries in PostgreSQL (since we're using Redis for boundary caching), or user authentication tables (separate from API keys)?

**Answer:** User can't think of any data to exclude - retain all relevant data.

### Existing Code to Reference

No similar existing features identified for reference.

### Follow-up Questions

No follow-up questions were needed.

## Visual Assets

### Files Provided:

No visual assets provided.

### Visual Insights:

No visual analysis performed.

## Requirements Summary

### Functional Requirements

**ONSUD Properties Table:**
- Store 41 million property records for Great Britain
- Include UPRN as primary identifier (bigint)
- Store BOTH raw OS Grid coordinates (eastings/northings as integers) AND converted WGS84 coordinates (latitude/longitude as decimal)
- Include ALL available ONS geography codes as separate columns:
  - Ward code
  - Electoral division code
  - Parish code
  - Westminster constituency code
  - LSOA (Lower Layer Super Output Area) code
  - MSOA (Middle Layer Super Output Area) code
  - OA (Output Area) code
  - Built-up Area code
  - Rural/Urban classification
  - County code
  - Region code
  - Any other geography codes available in ONSUD data
- Include created_at, updated_at, and deleted_at (soft delete) timestamps
- Support data updates every 6 weeks from ONS ONSUD releases

**ONSPD Postcodes Table:**
- Store postcode-level lookup data from ONS Postcode Directory
- Normalize all postcodes to standard format (uppercase with space, e.g., "SW1A 1AA")
- Include postcode as primary key (varchar)
- Store centroid coordinates (latitude/longitude as decimal for WGS84)
- Include geography codes (similar structure to ONSUD table)
- Include termination_date (nullable datetime) for terminated postcodes
- Include quality indicators from ONSPD
- Include created_at, updated_at, and deleted_at timestamps
- Support quarterly data updates from ONS ONSPD releases
- Automatically delete terminated postcodes after 12-month retention period

**ONS Geography Names Lookup Tables:**
- Translate GSS (Government Statistical Service) codes to human-readable names
- Support all geography types: wards, parishes, electoral divisions, constituencies, LSOAs, MSOAs, OAs, Built-up Areas, counties, regions, etc.
- Structure: Best practice approach chosen during implementation (separate tables vs. polymorphic table)
- Include GSS code as primary key
- Include human-readable name
- Include created_at, updated_at, and deleted_at timestamps
- Enable JOIN queries from ONSUD/ONSPD tables to retrieve readable geography names

**API Authentication System Tables:**
- API keys table for external user authentication
- Store hashed API key (unique)
- Include name/description for key identification
- Include rate_limit_tier for tiered rate limiting
- Include is_active boolean flag for enabling/disabling keys
- Track IP address that generated the key
- Track user/organization metadata (name, email, organization name, etc.)
- Include created_at, updated_at, deleted_at, and last_used_at timestamps
- Support API key creation, revocation, and listing via admin endpoints

**Usage Logs Table:**
- Log EVERY single API request (no sampling) for comprehensive analytics
- Include endpoint path (varchar)
- Include request_timestamp (datetime with microseconds)
- Include response_time_ms (integer)
- Include http_status_code (integer)
- Include api_key_id (nullable foreign key to api_keys table)
- Include ip_address (varchar)
- Include user_agent (text)
- Include error_message (nullable text) for failed requests
- Support queries for usage statistics, popular endpoints, and performance metrics
- Enable filtering by date range, endpoint, API key, status code

**Zero-Downtime Update Strategy:**
- Implement staging table approach for ONSUD and ONSPD updates
- Best practice solution for long-term maintainability (chosen during implementation)
- Support atomic production swap to prevent API downtime during data imports
- Enable rollback capability if validation fails after staging import
- Preserve data integrity during entire update process

### Reusability Opportunities

No existing components or similar features identified for reuse.

### Scope Boundaries

**In Scope:**
- PostgreSQL database schema design and migrations
- ONSUD properties table with full geography codes and both coordinate systems
- ONSPD postcodes table with normalized format and termination tracking
- ONS geography names lookup tables for all geography types
- API authentication tables (API keys with metadata)
- Usage logs table for comprehensive request tracking
- Comprehensive indexing strategy for efficient queries
- Soft delete support across all tables
- Staging table strategy for zero-downtime updates
- 12-month retention policy for terminated postcodes
- Laravel Eloquent models for all tables
- Database constraints (NOT NULL, UNIQUE, foreign keys) for data integrity
- Composite indexes for common query patterns

**Out of Scope:**
- Full postal address storage (requires AddressBase license)
- Boundary polygon geometries in PostgreSQL (handled by Redis caching)
- User authentication tables separate from API keys (API-only service)
- Actual data import scripts (covered in separate roadmap item #3)
- Automated cleanup jobs for 12-month postcode deletion (implementation logic)
- API endpoints using these tables (covered in separate roadmap items #4-6)
- Rate limiting middleware logic (covered in roadmap item #10)
- Redis cache schema or structure (covered in roadmap item #7)

### Technical Considerations

**Framework and ORM:**
- Laravel framework with Eloquent ORM
- Laravel migration files for schema definition
- PSR-12 coding standards for PHP
- Database constraints enforced at database level for data integrity

**Database Technology:**
- PostgreSQL 15+ as primary database
- B-tree indexes on all geography code columns and UPRN/postcode
- Composite indexes for common query patterns (e.g., ward_code + postcode, parish_code + postcode)
- Consider spatial indexes if geometry columns added in future (currently out of scope)
- Foreign key constraints between api_keys and usage_logs tables
- Appropriate data types: bigint for UPRN, varchar for codes, decimal for coordinates, datetime for timestamps

**Data Volume Considerations:**
- ONSUD table: 41 million rows (requires efficient indexing)
- ONSPD table: ~1.7 million postcodes
- Usage logs table: high write volume (every request logged)
- Consider table partitioning for usage_logs if volume becomes issue (future optimization)

**Migration Strategy:**
- Reversible migrations with rollback/down methods
- Small, focused changes per migration file
- Separate schema changes from data migrations
- Never modify existing migrations after deployment
- Clear naming conventions for migration files

**Soft Delete Implementation:**
- deleted_at column (nullable datetime) on all tables
- Laravel soft delete trait on all Eloquent models
- Queries automatically exclude soft-deleted records unless explicitly included
- Enables data recovery and audit trails

**Staging Table Approach:**
- Best practice for zero-downtime: use table suffixes or schema-based versioning
- Atomic swap via table renaming or schema switching
- Validation queries run on staging data before production swap
- Rollback capability by reverting to previous production table

**Postcode Normalization:**
- Store all postcodes in uppercase with space (e.g., "SW1A 1AA")
- Normalize input during import and API queries
- Ensure consistent format for reliable lookups

**Timestamp Requirements:**
- created_at and updated_at on all tables (Laravel conventions)
- deleted_at for soft deletes
- last_used_at for API keys (track activity)
- termination_date for postcodes (12-month retention tracking)

**Geography Code Completeness:**
- Include ALL available ONS geography codes even if not immediately used
- Enables future feature expansion without schema changes
- Columns should be nullable if not all properties have all codes

**API Key Security:**
- Store hashed API keys, not plaintext
- Use bcrypt or similar one-way hashing algorithm
- Validate keys via hash comparison during authentication

**Usage Logs Performance:**
- High-volume table with every request logged
- Consider indexes on commonly queried columns: request_timestamp, endpoint, api_key_id, http_status_code
- Consider future partitioning strategy by date if table grows excessively large

**Data Integrity:**
- NOT NULL constraints on required fields (UPRN, postcode, api_key)
- UNIQUE constraints on UPRN, postcode, api_key hash
- Foreign key constraints with appropriate cascade behaviors
- Check constraints for valid coordinate ranges if applicable
