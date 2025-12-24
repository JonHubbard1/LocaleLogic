# LocaleLogic

## Project Overview
LocaleLogic is a UK geography microservice and REST API that provides postcode and property-level lookups, returning electoral, administrative, and spatial data. It serves as a dedicated geography data service that other applications consume via API.

**Core Function**: Given a postcode, return all associated geography information (ward, parish, constituency, local authority, etc.) along with the coordinates of every property in that postcode for mapping purposes.

## Primary Use Cases

1. **Casework Routing** — A constituent enters their postcode, the system returns their ward/division/parish, and the case is automatically assigned to the correct councillor
2. **Leaflet Delivery Route Mapping** — Councillors select postcodes for a delivery route, the system returns coordinates for all properties, which are plotted on OpenStreetMap via Leaflet.js to visualise walking routes
3. **Parish/Town Councillor Support** — Parish councillors cannot access the electoral register, so this service lets them query property counts by postcode within their parish for planning leaflet deliveries
4. **Boundary Visualisation** — Fetch GeoJSON boundary polygons for wards, parishes, or divisions to display "this is your patch" on a map

## Code Generation

Use Ollama tools for all code generation, refactoring, and test writing:
- ollama_generate_code / ollama_generate_code_with_context for new code
- ollama_refactor_code for improvements
- ollama_fix_code for bug fixes
- ollama_write_tests for tests
- ollama_review_file for code reviews

Use Claude's own reasoning for planning, architecture, and complex problem-solving.

## Data Sources

- **ONSUD (ONS UPRN Directory)** — 41 million property records for Great Britain, updated 6-weekly, containing UPRN, OS Grid coordinates, and geography codes
- **ONS Names/Codes files** — CSV files translating GSS codes (e.g., E05001234) to human-readable names (e.g., "Melksham South")
- **ONS Open Geography API** — Boundary polygons fetched on-demand and cached locally

## Technology Stack

- **Framework**: Laravel 12+ (full framework, not Lumen)
- **Language**: PHP 8.3+
- **Database**: PostgreSQL 15+
- **Caching**: Redis for frequently-accessed lookups and boundary cache
- **Queue**: Redis or database queue for background import jobs
- **HTTP Client**: Laravel HTTP facade for ONS API calls
- **Coordinate Conversion**: proj4php library (OS Grid EPSG:27700 → WGS84 EPSG:4326)

## Architecture Patterns

- **Repository Pattern** — All database queries through repository interfaces
- **Service Classes** — Business logic in dedicated service classes
- **DTOs** — Type-safe data transfer objects for API responses
- **Action Classes** — Single-purpose classes for discrete operations

## Main Goals

1. **Reliable Data Foundation** — Store and serve 41 million property records with sub-200ms response times
2. **Automated Updates** — Detect and import new ONSUD releases (6-weekly) with zero downtime using table-swap strategy
3. **Clean REST API** — Simple, well-documented endpoints for postcode lookups, property queries, and boundary retrieval
4. **Accurate Boundaries** — Cache high-quality (BFC - Full Resolution) boundary polygons from ONS API
5. **Multi-Application Support** — Serve CaseMate initially, designed to support other applications via API keys

## Hosting Environment

- **Dedicated VPS**: 4 cores, 8GB RAM, 150GB disk
- **Expected database size**: ~15-20GB including indexes
- **Single-tenant initially**, API authentication for future multi-tenant use

## Key Technical Considerations

- **No address storage** — We store UPRNs and coordinates only, not actual addresses (which would require AddressBase licence)
- **Coordinate conversion** — ONSUD provides OS Grid eastings/northings; we pre-convert to WGS84 lat/lng during import for faster API responses
- **Geography codes** — All use 9-character GSS codes (e.g., E05001234 for wards, E06000054 for Wiltshire)
- **Welsh language support** — Store both English and Welsh names for Welsh geographies

## Project Structure Goals

- Domain-driven organisation where appropriate
- Comprehensive test coverage (Pest PHP)
- PHPStan Level 8 for static analysis
- PSR-12 coding standards

## Development Notes

### Important Reminders
- Always use repository pattern for database queries
- All coordinates stored as WGS84 (lat/lng), not OS Grid
- GSS codes are 9 characters (e.g., E05001234)
- No personal address data - UPRNs and coordinates only
- Sub-200ms response time target for API endpoints
- Table-swap strategy for zero-downtime ONSUD updates

### API Design Principles
- RESTful endpoints
- Consistent JSON response format
- Proper HTTP status codes
- API key authentication (future)
- Rate limiting consideration (future)
