# Product Roadmap

1. [ ] Database Schema and Core Models — Create PostgreSQL database schema for ONSUD (properties table with UPRN, coordinates, geography codes), ONSPD (postcodes table), ONS geography names lookup, and API authentication tables (API keys, usage logs). Include indexes for efficient postcode/UPRN/geography code queries. `M`

2. [ ] Coordinate Conversion Utility — Implement OS National Grid to WGS84 latitude/longitude conversion function using established conversion algorithms (Helmert transformation). Validate accuracy against known test coordinates from Ordnance Survey documentation. `S`

3. [ ] Manual Data Import Scripts — Build command-line scripts to download ONSUD and ONSPD CSV files from ONS, parse and validate format, and import to PostgreSQL tables with progress tracking and error reporting. Include staging table support for zero-downtime swaps. `M`

4. [ ] Postcode Geography Lookup Endpoint — Implement GET /postcode/{postcode} endpoint returning all geography codes (ward, division, parish, constituency, LSOA, MSOA, etc.) with human-readable names joined from ONS Names/Codes tables. Include input validation, error handling, and JSON response formatting. `S`

5. [ ] Property Coordinate Lookup Endpoint — Implement GET /postcode/{postcode}/properties endpoint returning array of all UPRNs with WGS84 coordinates for properties within specified postcode. Support pagination for postcodes with 100+ properties. `S`

6. [ ] Administrative Area Property Query Endpoints — Implement GET /parish/{code}/properties, GET /ward/{code}/properties, and GET /division/{code}/properties endpoints returning property counts and optionally full coordinate lists for specified geography. Include query parameter for count-only vs. full data responses. `M`

7. [ ] Redis Cache Layer — Integrate Redis for caching frequently-accessed postcode lookups and boundary GeoJSON responses. Implement cache invalidation strategy triggered by data updates. Configure TTL policies and cache warming for common lookups. `S`

8. [ ] Boundary Visualization Endpoint — Implement GET /boundary/{type}/{code} endpoint fetching GeoJSON polygons from ONS Open Geography API on first request, storing in Redis cache, and returning cached version on subsequent requests. Support ward, parish, division, constituency boundary types. `M`

9. [ ] API Authentication System — Implement API key generation, storage, and validation middleware. Support both API key authentication (external users) and IP whitelist (private network). Include admin endpoints for key creation, revocation, and listing. `M`

10. [ ] Rate Limiting System — Implement configurable rate limiting per API key with Redis-backed counters. Support administrator-configurable limits (requests per minute/hour/day) per key. Exempt private network IPs from rate limiting. Return HTTP 429 with Retry-After header when exceeded. `S`

11. [ ] Usage Logging and Analytics — Implement middleware logging all API requests to database with endpoint, timestamp, response time, authentication method, API key/IP, HTTP status code, and error details. Create admin dashboard endpoint showing usage statistics, popular endpoints, and performance metrics. `M`

12. [ ] Automated ONSUD Import Pipeline — Build scheduled job (every 6 weeks) detecting new ONSUD releases via ONS website scraping or RSS feed monitoring, downloading ZIP file, validating format/integrity, importing to staging table, running validation queries, and performing atomic production swap with rollback on errors. `L`

13. [ ] Automated ONSPD Import Pipeline — Build scheduled job (quarterly) detecting new ONSPD releases, downloading, validating, importing to staging, and swapping to production. Include postcode termination handling (mark terminated postcodes, preserve historical data). `M`

14. [ ] Update Notification System — Implement email and webhook notifications for data import success/failure events. Include import summary statistics (records added/updated/removed), validation warnings, and administrator action prompts for failures. Configure SMTP or third-party email service integration. `S`

15. [ ] Health Check and Monitoring Endpoints — Implement GET /health endpoint returning database connectivity status, Redis availability, last ONSUD/ONSPD import timestamps, cache hit rates, and overall system status. Support detailed and summary response modes for external monitoring tool integration. `S`

16. [ ] API Documentation — Generate OpenAPI/Swagger documentation for all endpoints with request/response examples, authentication requirements, rate limit information, and error code reference. Host interactive API explorer at /docs endpoint. `S`

17. [ ] Deployment and Environment Configuration — Configure production deployment on dedicated VPS with environment variables for database credentials, Redis connection, ONS API keys, SMTP settings, and rate limit defaults. Set up automated backups, log rotation, and system monitoring. `M`

18. [ ] Performance Optimization and Indexing — Analyze slow query logs, add additional database indexes for common query patterns, optimize coordinate conversion performance, implement database connection pooling, and tune Redis cache policies based on production usage patterns. `M`

> Notes
> - Order items by technical dependencies and product architecture
> - Each item should represent an end-to-end (frontend + backend) functional and testable feature
