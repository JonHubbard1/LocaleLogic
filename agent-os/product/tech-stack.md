# Tech Stack

## Framework & Runtime
- **Application Framework:** Laravel or Lumen (lightweight Laravel for API-focused applications)
- **Language/Runtime:** PHP 8.2+
- **Package Manager:** Composer

## API & Response Formats
- **API Architecture:** RESTful API
- **Response Formats:** JSON (standard data lookups), GeoJSON (spatial/boundary data)
- **API Documentation:** OpenAPI/Swagger specification with interactive explorer

## Database & Storage
- **Database:** PostgreSQL 15+ (primary choice) or MySQL 8.0+ (acceptable alternative)
- **ORM/Query Builder:** Laravel Eloquent ORM and Query Builder
- **Caching:** Redis 7.0+ for API response caching, boundary GeoJSON caching, and rate limiting counters
- **Database Features:** Spatial indexes for coordinate queries, B-tree indexes for postcode/UPRN/geography code lookups

## Data Sources
- **ONSUD (ONS UPRN Directory):** 41 million property records for Great Britain, updated every 6 weeks, containing UPRN, OS Grid coordinates, and geography codes
- **ONSPD (ONS Postcode Directory):** Postcode-level lookups with geography codes and centroid coordinates, updated quarterly
- **ONS Names/Codes Files:** Lookup tables translating GSS codes to human-readable geography names (wards, parishes, divisions, constituencies, etc.)
- **ONS Open Geography API:** Boundary polygon GeoJSON fetched on-demand and cached locally in Redis

## Coordinate Systems
- **Input Format:** OS National Grid (eastings/northings) from ONSUD
- **Output Format:** WGS84 latitude/longitude for web mapping compatibility
- **Conversion:** Helmert transformation algorithm for OS Grid to WGS84 conversion

## Authentication & Security
- **API Authentication:** API key/token system for external users
- **Network Access:** IP whitelist for private network (no authentication required for internal applications)
- **Rate Limiting:** Redis-backed rate limiting with administrator-configurable limits per API key
- **Environment Configuration:** Environment variables for all credentials, API keys, and sensitive configuration (never committed to version control)

## Logging & Monitoring
- **Usage Logging:** Database-persisted logs for all API requests including endpoint, timestamp, response time, authentication method, caller identification, and HTTP status
- **Application Logging:** Laravel log files for errors, warnings, and debugging (daily rotation)
- **Health Monitoring:** Health check endpoints reporting database connectivity, Redis status, data currency, and cache performance
- **Error Tracking:** Centralized error handling with detailed error logging and administrator notifications

## Automation & Scheduling
- **Task Scheduler:** Laravel scheduler (cron-based) for automated data import pipelines
- **ONSUD Import:** Scheduled job every 6 weeks detecting new releases, downloading, validating, importing to staging, and performing zero-downtime production swap
- **ONSPD Import:** Scheduled job quarterly for postcode directory updates
- **Queue System:** Laravel queue for background jobs (data imports, boundary fetching, notifications)

## Notifications
- **Email Notifications:** SMTP or third-party email service (SendGrid, Postmark, Mailgun) for data import success/failure alerts
- **Webhook Notifications:** HTTP POST webhooks for integration with external monitoring systems
- **Notification Events:** Import success with statistics, import failures with error details, validation warnings, data format changes detected

## Deployment & Infrastructure
- **Hosting:** Dedicated VPS - 4 CPU cores, 8GB RAM, 150GB disk
- **Web Server:** Nginx (reverse proxy and static file serving)
- **Process Manager:** Supervisor for Laravel queue workers and scheduler
- **Backups:** Automated PostgreSQL backups (daily full, hourly incremental)
- **Log Rotation:** Automated log rotation and archival to prevent disk space exhaustion

## Development & Quality
- **Version Control:** Git with feature branches and descriptive commit messages
- **Code Style:** PSR-12 coding standard for PHP
- **Linting/Formatting:** PHP CS Fixer or Laravel Pint for automated code formatting
- **Dependency Management:** Composer with lock file for reproducible builds, minimal dependencies, documented rationale for major packages

## Testing Strategy
- **Unit Tests:** PHPUnit for business logic, coordinate conversion algorithms, data validation
- **Integration Tests:** API endpoint tests with test database, mocked ONS API responses
- **Performance Tests:** Load testing for high-volume postcode lookups and concurrent boundary requests
- **Data Validation Tests:** Automated validation of imported ONSUD/ONSPD data integrity and format compliance

## Data Licensing
- **ONSUD License:** Open Government Licence v3.0 - commercial use permitted with attribution
- **ONSPD License:** Open Government Licence v3.0 - commercial use permitted with attribution
- **Boundary Data License:** Open Government Licence v3.0 via ONS Open Geography API
- **Attribution Requirement:** Include ONS attribution in API documentation and responses where appropriate

## What LocaleLogic Does NOT Include
- **Full Address Storage:** Only stores UPRNs and coordinates, not full postal addresses (AddressBase license required for addresses)
- **Complex Spatial Queries:** Not a full GIS system; focuses on basic coordinate lookups and boundary visualization
- **Consumer-Facing UI:** API-only product; consuming applications (like CaseMate) provide user interfaces
- **Real-time ONS API Proxying:** Boundaries are cached locally; does not proxy every request to ONS infrastructure
