# Task 1: Dependencies and Service Infrastructure

## Overview
**Task Reference:** Task #1 from `/home/ploi/dev.localelogic.uk/agent-os/specs/2025-12-23-database-schema-and-core-models/tasks.md`
**Implemented By:** API Engineer
**Date:** 2025-12-23
**Status:** Complete

### Task Description
Implement the foundation layer for the LocaleLogic database schema project by installing the proj4php coordinate transformation library and creating the CoordinateConverter service class. This service enables conversion of British National Grid (EPSG:27700) coordinates to WGS84 (EPSG:4326) latitude/longitude, which is essential for transforming the 41 million ONSUD property records into API-ready formats.

## Implementation Summary
Successfully implemented a robust coordinate transformation service that converts OS Grid eastings/northings to WGS84 lat/lng coordinates. The implementation uses the proj4php library for accurate coordinate transformations and includes comprehensive error handling for invalid coordinate ranges. The service is optimized for both single conversions (for API responses) and batch conversions (for bulk data imports) by caching projection definitions to minimize initialization overhead.

All 5 focused tests pass successfully, validating conversion accuracy against known coordinates (London Eye), proper error handling for invalid inputs, and correct precision matching the database schema specification (decimal 9,6 format).

## Files Changed/Created

### New Files
- `/home/ploi/dev.localelogic.uk/composer.json` - Project dependency configuration with proj4php library requirement
- `/home/ploi/dev.localelogic.uk/app/Services/CoordinateConverter.php` - Main service class for coordinate transformations
- `/home/ploi/dev.localelogic.uk/tests/Unit/Services/CoordinateConverterTest.php` - Unit tests for coordinate conversion functionality

### Modified Files
None - This is a fresh implementation with no existing files modified.

### Deleted Files
None

## Key Implementation Details

### CoordinateConverter Service Class
**Location:** `/home/ploi/dev.localelogic.uk/app/Services/CoordinateConverter.php`

The service class initializes projection objects for EPSG:27700 (British National Grid) and EPSG:4326 (WGS84) in the constructor, caching them as private properties. This design ensures projection definitions are created once per service instance and reused across multiple conversions, minimizing computational overhead during bulk operations.

Key features:
- **osGridToWgs84()** method converts single coordinate pairs with validation
- **batchConvert()** method optimizes bulk conversions by reusing projection objects
- **validateCoordinates()** private method ensures inputs are within valid British National Grid bounds (0-700000 easting, 0-1300000 northing)
- Results are rounded to 6 decimal places matching the database schema specification (DECIMAL(9,6))
- Comprehensive error handling with descriptive exception messages including the invalid coordinate values

**Rationale:** The caching strategy directly addresses the spec requirement to "minimize overhead by reusing projection object" during ONSUD import operations that will process millions of records.

### Test Suite Implementation
**Location:** `/home/ploi/dev.localelogic.uk/tests/Unit/Services/CoordinateConverterTest.php`

Implemented 5 focused tests covering critical conversion behaviors:
1. **test_converts_os_grid_to_wgs84_accurately** - Validates conversion accuracy using London Eye coordinates as a known reference point
2. **test_batch_convert_returns_array_of_results** - Verifies batch conversion returns correct array structure with lat/lng keys
3. **test_throws_exception_for_invalid_easting** - Ensures proper error handling for out-of-range easting values
4. **test_throws_exception_for_invalid_northing** - Ensures proper error handling for out-of-range northing values
5. **test_result_precision_matches_specification** - Confirms results can be formatted to 6 decimal places per spec

**Rationale:** These tests focus exclusively on critical behaviors per the testing standards, avoiding exhaustive edge case coverage while ensuring the service meets all acceptance criteria.

### Dependency Management
**Location:** `/home/ploi/dev.localelogic.uk/composer.json`

Created composer.json with Laravel 10 framework and proj4php ^2.0 library dependencies. The configuration includes:
- PSR-4 autoloading for App, Database, and Tests namespaces
- PHPUnit 10 for testing infrastructure
- Optimized autoloader configuration for performance

**Rationale:** Using Composer's standard package management aligns with Laravel conventions and ensures reproducible builds across development environments.

## Database Changes
Not applicable - This task focuses on service infrastructure, not database schema.

## Dependencies

### New Dependencies Added
- `proj4php/proj4php` (^2.0.19) - Coordinate transformation library for converting between EPSG:27700 and EPSG:4326 projections
- `laravel/framework` (^10.0) - Application framework foundation
- `phpunit/phpunit` (^10.0) - Testing framework for unit tests

### Configuration Changes
None - No environment variables or configuration files were modified beyond composer.json.

## Testing

### Test Files Created/Updated
- `/home/ploi/dev.localelogic.uk/tests/Unit/Services/CoordinateConverterTest.php` - 5 focused unit tests for CoordinateConverter service

### Test Coverage
- Unit tests: Complete (5 tests covering all critical behaviors)
- Integration tests: Not applicable at this stage
- Edge cases covered: Invalid easting, invalid northing, conversion accuracy validation

### Manual Testing Performed
Executed the test suite using PHPUnit to verify all tests pass:
```bash
./vendor/bin/phpunit tests/Unit/Services/CoordinateConverterTest.php --testdox
```

Results: 5 tests, 19 assertions, all passing in 0.807 seconds.

Validated conversion accuracy by comparing results against known coordinates:
- London Eye (530457, 179934) converts to approximately (51.503, -0.120) - within acceptable delta

## User Standards & Preferences Compliance

### Global Coding Style Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/global/coding-style.md`

**How Implementation Complies:**
The CoordinateConverter service uses descriptive method names (osGridToWgs84, batchConvert) that reveal intent without abbreviations. Functions are kept focused on single tasks, with validation logic extracted to a private method. No dead code or commented-out blocks were included.

**Deviations:** None

### Global Error Handling Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/global/error-handling.md`

**How Implementation Complies:**
The service validates input early (fail fast principle) and throws InvalidArgumentException with specific, actionable error messages that include the invalid coordinate values for debugging. The service wraps proj4php library errors with additional context. Resource cleanup is handled automatically by PHP's garbage collection.

**Deviations:** None

### Global Commenting Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/global/commenting.md`

**How Implementation Complies:**
Added minimal PHPDoc comments to explain large sections of logic (class purpose, method parameters/returns). Comments are evergreen and informational rather than describing recent changes or fixes. The code structure itself is self-documenting through clear naming conventions.

**Deviations:** None

### Backend API Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/backend/api.md`

**How Implementation Complies:**
While this task doesn't create API endpoints, the CoordinateConverter service is designed to support API responses by returning standardized array structures with 'lat' and 'lng' keys. This consistent data structure will integrate seamlessly with future API endpoint implementations.

**Deviations:** None

### Testing Standards
**File Reference:** `/home/ploi/dev.localelogic.uk/agent-os/standards/testing/test-writing.md`

**How Implementation Complies:**
Wrote only 5 focused tests covering critical core user flows (coordinate conversion, batch processing, error handling). Tests validate behavior rather than implementation details. Deferred edge case testing per the standard to focus on completing feature implementation first. Tests execute in milliseconds for fast feedback during development.

**Deviations:** None

## Integration Points

### APIs/Endpoints
Not applicable - This service will be consumed by future API endpoints and data import services but does not expose HTTP endpoints itself.

### External Services
- **proj4php Library:** Used for coordinate transformation between EPSG:27700 and EPSG:4326 projections

### Internal Dependencies
- Will be consumed by future ONSUD data import service (Task Group 6) for batch coordinate conversion during property data ingestion
- Will be used by property-related API endpoints (future roadmap items 4-6) for real-time coordinate conversion in API responses

## Known Issues & Limitations

### Issues
None identified during implementation.

### Limitations
1. **Limited Coordinate System Support**
   - Description: Service currently supports only EPSG:27700 to EPSG:4326 conversion
   - Reason: Specification requires only British National Grid to WGS84 conversion for ONSUD property data
   - Future Consideration: Could be extended to support additional coordinate systems if requirements expand to include Northern Ireland (Irish Grid) or other territories

2. **Validation Range Hardcoded**
   - Description: British National Grid valid ranges are defined as class constants
   - Reason: These ranges are well-established standards that won't change
   - Future Consideration: If supporting multiple coordinate systems in the future, validation logic may need to be projection-specific

## Performance Considerations
The service is optimized for bulk operations by:
- Caching projection objects in the constructor to avoid repeated initialization
- Using efficient proj4php transform() method for conversion calculations
- Rounding results to 6 decimal places (sufficient for property location accuracy while minimizing storage)

Expected performance: The batchConvert() method can process thousands of coordinate pairs per second, suitable for importing 41 million ONSUD property records within reasonable timeframes.

## Security Considerations
Input validation prevents injection of invalid coordinate values that could cause computation errors. The service throws exceptions for out-of-range inputs rather than attempting to process invalid data. No user-supplied data is executed or interpolated into SQL queries (this service performs mathematical transformations only).

## Dependencies for Other Tasks
This implementation satisfies the dependency for:
- **Task Group 2:** Geography Lookup Tables and Migrations (depends on Task Group 1)
- **Task Group 3:** Properties Tables (will use coordinate conversion during data import)
- **Future ONSUD Import Service:** Will use batchConvert() method for transforming 41M property coordinates

## Notes
The proj4php library version 2.0.19 was installed successfully and includes built-in projection definitions for EPSG:27700 and EPSG:4326, eliminating the need for custom projection string definitions. This simplifies the implementation while maintaining accuracy.

The London Eye coordinate test (530457, 179934) was chosen as a validation reference because it's a well-known landmark with publicly available precise coordinates, making it easy to verify conversion accuracy against external sources.

All acceptance criteria from the task specification have been met:
- 5 tests written and passing
- proj4php library installed and accessible via Composer
- Single coordinate conversion works correctly with validated accuracy
- Batch conversion processes multiple coordinates efficiently
- Error handling provides clear, descriptive messages with coordinate context
