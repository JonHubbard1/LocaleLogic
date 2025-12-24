# Product Mission

## Pitch
LocaleLogic is a UK geography microservice and API that helps developers and internal applications perform postcode and property-level lookups by providing fast, reliable access to electoral, administrative, and spatial data from authoritative UK government sources.

## Users

### Primary Customers
- **CaseMate Application**: Councillor casework management system requiring automated constituent routing based on geography
- **Internal Technoliga Applications**: Easy Youth Club and future products needing UK geography data integration
- **Future External Developers**: Third-party developers and organisations requiring reliable UK geography lookups via authenticated API

### User Personas

**Application Developer** (25-45 years)
- **Role:** Full-stack developer building citizen-facing or internal government applications
- **Context:** Building applications that need to route users, visualize boundaries, or understand UK administrative geography
- **Pain Points:** Complex ONS data formats, frequent updates requiring manual intervention, lack of ready-to-use APIs for UK geography data, coordinate system conversions
- **Goals:** Integrate reliable geography lookups with minimal effort, ensure data stays current automatically, focus on application features rather than data management

**Councillor/Caseworker** (30-65 years)
- **Role:** Elected representative or casework support staff handling constituent issues
- **Context:** Receives inquiries from constituents and needs to verify jurisdiction, route cases correctly, and plan community engagement
- **Pain Points:** Uncertain which councillor serves a specific address, difficulty planning leaflet delivery routes, parish councillors lack access to electoral register for property counts
- **Goals:** Instantly identify correct ward/division/parish for any postcode, generate printable maps showing property locations for canvassing, understand parish boundaries and property counts

## The Problem

### Fragmented and Complex UK Geography Data
UK government publishes authoritative geography data (ONSUD, ONSPD, boundary files) across multiple sources with different update schedules, formats, and coordinate systems. Developers must manually download quarterly/6-weekly updates, parse complex CSV formats, handle coordinate conversions (OS Grid to WGS84), and maintain boundary caches. This results in outdated data, integration delays, and duplicated effort across multiple applications.

**Our Solution:** LocaleLogic consolidates ONS data sources into a single REST API with automated update pipelines, coordinate conversion, boundary caching, and zero-downtime deployments. Applications get current, consistent geography data through simple HTTP requests.

### Manual Data Update Burden
ONS releases ONSUD updates every 6 weeks and ONSPD updates quarterly. Without automation, developers must monitor release schedules, download multi-gigabyte files, validate integrity, update databases, and coordinate deployments. Missing updates means serving outdated geography codes, causing incorrect case routing and boundary visualizations.

**Our Solution:** Automated import pipelines detect new ONS releases, download and validate data, import to staging tables, and perform zero-downtime production swaps. Notifications alert administrators of successful updates or failures requiring intervention.

### Inefficient Boundary Data Access
Fetching GeoJSON boundary polygons for every request from ONS Open Geography API is slow (500ms-2s per request) and creates unnecessary load on ONS infrastructure. Without local caching, mapping features become unusably slow, especially when visualizing multiple boundaries or generating print-ready maps.

**Our Solution:** LocaleLogic caches boundary GeoJSON locally in Redis after first fetch, reducing subsequent requests to <10ms. Cache invalidation occurs on ONSUD/ONSPD updates to ensure boundary accuracy.

## Differentiators

### Automated Data Currency
Unlike manually-managed geography databases, LocaleLogic automatically detects and imports ONS updates every 6 weeks (ONSUD) and quarterly (ONSPD) with zero-downtime table swaps. This results in applications always having current geography codes without developer intervention.

### Purpose-Built for UK Local Government
Unlike generic GIS systems or international geocoding services, LocaleLogic focuses specifically on UK electoral and administrative geography at property-level granularity. Returns ward, division, parish, constituency, LSOA, MSOA, and other ONS geography codes essential for local government workflows.

### Property-Level Granularity Without Addresses
Unlike AddressBase (which requires expensive licensing), LocaleLogic provides UPRN-level coordinate lookups under Open Government Licence. Applications can map individual properties within postcodes for route planning without storing full addresses or paying per-lookup fees.

### Integrated Boundary Visualization
Unlike standalone postcode APIs, LocaleLogic integrates ONS boundary data with coordinate lookups. Single API provides both "which ward is this postcode in?" and "show me the ward boundary polygon" in Leaflet-ready GeoJSON format.

## Key Features

### Core Features
- **Postcode Geography Lookup:** Enter any UK postcode and receive all associated geography codes (ward, electoral division, parish, constituency, LSOA, MSOA, county, etc.) with human-readable names from ONS registers
- **Property Coordinate Lookup:** Retrieve WGS84 latitude/longitude coordinates for all UPRNs within a postcode, enabling route mapping and leaflet delivery planning in Leaflet.js or similar mapping libraries
- **Administrative Area Property Queries:** Query all properties within a ward, parish, or electoral division by geography code, returning property counts and coordinates for jurisdiction-level analysis
- **Automated Data Updates:** Background pipeline monitors ONS releases every 6 weeks (ONSUD) and quarterly (ONSPD), automatically downloading, validating, importing to staging, and performing zero-downtime production table swaps

### API Features
- **REST API with JSON/GeoJSON Responses:** Standard REST endpoints returning JSON for data lookups and GeoJSON for spatial/boundary data, with consistent error handling and HTTP status codes
- **API Authentication System:** Support for API key/token authentication enabling controlled external access while allowing unrestricted private network usage for internal applications
- **Configurable Rate Limiting:** Administrator-configurable rate limits per API key for external users, with no limits for private network connections to prevent abuse while maintaining performance for internal tools
- **Usage Logging and Analytics:** Comprehensive logging of endpoint access, response times, authentication method, and caller identification for performance monitoring, capacity planning, and usage analysis

### Spatial Features
- **Boundary Visualization:** Fetch cached GeoJSON polygons for any ward, parish, electoral division, or constituency boundary from ONS Open Geography API with Redis caching for sub-10ms response times
- **Coordinate System Conversion:** Automatic conversion from OS National Grid (eastings/northings in ONSUD) to WGS84 latitude/longitude for direct integration with web mapping libraries like Leaflet.js and Google Maps
- **Postcode Centroid Coordinates:** Return postcode-level centroid coordinates from ONSPD for applications requiring postcode-level (rather than property-level) mapping precision

### Operational Features
- **Zero-Downtime Updates:** Staging table import and atomic production swap ensures API availability throughout data update processes, with automatic rollback on validation failures
- **Update Notifications:** Email/webhook notifications to administrators on successful data imports, failed imports requiring intervention, or ONS data format changes detected during validation
- **Health Monitoring:** API health check endpoints reporting database connectivity, Redis cache status, data currency (last ONSUD/ONSPD import date), and overall system status for external monitoring tools
