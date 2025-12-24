# LocaleLogic API Documentation

Welcome to the LocaleLogic API documentation. This directory contains comprehensive guides for integrating with our APIs.

## Available Documentation

### 📘 [API Documentation](./API.md)
Complete reference for the Postcode Lookup API including:
- Authentication details
- Endpoint specifications
- Response formats
- Error handling
- Examples and use cases
- Technical implementation notes

**Best for**: First-time integration, comprehensive reference, troubleshooting

---

### ⚡ [Quick Reference Guide](./API_QUICK_REFERENCE.md)
Condensed cheat sheet with:
- Common request patterns
- Response structure
- Error codes table
- Quick copy-paste examples

**Best for**: Daily development, quick lookups, experienced users

---

### 🔧 [OpenAPI Specification](./openapi.yaml)
Machine-readable API specification in OpenAPI 3.0 format:
- Import into Swagger UI for interactive documentation
- Generate client SDKs automatically
- Integrate with API testing tools
- Validate requests and responses

**Best for**: API tooling integration, SDK generation, automated testing

---

### 📮 [Postman Collection](./postman_collection.json)
Ready-to-use Postman collection with:
- Pre-configured requests for all endpoints
- Error scenario examples
- Environment variables setup
- Request descriptions and examples

**Best for**: Manual testing, team sharing, API exploration

**To use**: Import into Postman → Set `api_token` variable → Start testing

---

## Getting Started

1. **Obtain an API Token**
   ```bash
   php artisan api:create-token your@email.com
   ```

2. **Make Your First Request**
   ```bash
   curl "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA" \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

3. **Explore the Response**
   - Geography hierarchy (ward → region)
   - Coordinates (WGS84 & OS Grid)
   - Property count
   - Optional UPRNs

---

## API Versions

- **v1** (Current): Postcode Lookup API - Full geography data with optional UPRNs

---

## Support

- **Email**: api-support@localelogic.uk
- **Documentation Issues**: Contact your account manager
- **Service Status**: https://status.localelogic.uk (if available)

---

## Additional Resources

### Internal Documentation
- [Implementation Plan](../.claude/plans/) - Original design and architecture decisions
- [Service Layer](../app/Services/PostcodeLookupService.php) - Core business logic
- [Controller](../app/Http/Controllers/Api/V1/PostcodeController.php) - API endpoint handler

### External Resources
- [UK Postcode Format](https://en.wikipedia.org/wiki/Postcodes_in_the_United_Kingdom)
- [GSS Coding](https://www.ons.gov.uk/methodology/geography/geographicalproducts/namescodesandlookups)
- [EPSG:4326 (WGS84)](https://epsg.io/4326)
- [EPSG:27700 (British National Grid)](https://epsg.io/27700)
