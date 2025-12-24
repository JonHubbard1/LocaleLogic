# Postcode Lookup API Documentation

Version 1.0 | Last Updated: December 2024

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Base URL](#base-url)
- [Endpoints](#endpoints)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Examples](#examples)
- [Rate Limiting](#rate-limiting)

---

## Overview

The Postcode Lookup API provides comprehensive geographic and administrative data for UK postcodes. The API returns coordinates (WGS84 and OS Grid), administrative geography hierarchies, property counts, and optional property identifiers (UPRNs).

### Key Features

- **Full Geography Data**: Ward, constituency, local authority, region, and more
- **Multiple Coordinate Systems**: WGS84 (latitude/longitude) and British National Grid
- **Optional UPRN Lists**: Include property identifiers on demand
- **Normalized Postcodes**: Accepts postcodes in any format (uppercase, lowercase, with/without spaces)
- **High Performance**: Sub-100ms response times with indexed lookups

---

## Authentication

All API requests require authentication using Laravel Sanctum tokens.

### Token Format

Include your API token in the `Authorization` header using the Bearer scheme:

```http
Authorization: Bearer YOUR_API_TOKEN
```

### Obtaining a Token

API tokens are generated via the command line by administrators:

```bash
php artisan api:create-token your@email.com
```

Store your token securely. Tokens do not expire but can be revoked if compromised.

---

## Base URL

```
https://dev.localelogic.uk/api/v1
```

All endpoints are relative to this base URL.

---

## Endpoints

### Get Postcode Data

Retrieve comprehensive geographic and administrative data for a UK postcode.

```http
GET /postcodes/{postcode}
```

#### Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `postcode` | string | Path | Yes | UK postcode (any format: uppercase, lowercase, with/without spaces) |
| `include` | string | Query | No | Additional data to include. Accepts: `uprns` |

#### Postcode Format

The API accepts postcodes in various formats:
- `SW1A1AA` (no space)
- `SW1A 1AA` (with space)
- `sw1a 1aa` (lowercase)
- `SW1a 1Aa` (mixed case)

All formats are normalized internally to the standard 8-character format.

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": {
    "postcode": "SW1A 1AA",
    "coordinates": {
      "wgs84": {
        "latitude": 51.495407,
        "longitude": -0.141515
      },
      "os_grid": {
        "easting": 529000,
        "northing": 179000
      }
    },
    "geography": {
      "ward": {
        "code": "E05013806",
        "name": "St James's"
      },
      "county_electoral_division": null,
      "parish": {
        "code": "E04012690",
        "name": "Melksham Without"
      },
      "local_authority_district": {
        "code": "E09000033",
        "name": "Westminster"
      },
      "constituency": {
        "code": "E14000639",
        "name": "Cities of London and Westminster"
      },
      "region": {
        "code": "E12000007",
        "name": "London"
      },
      "police_force_area": {
        "code": "E23000001",
        "name": "Metropolitan Police"
      }
    },
    "property_count": 142,
    "uprns": [100023336161, 100023336162]
  },
  "meta": {
    "api_version": "1.0",
    "timestamp": "2025-12-24T00:44:18+00:00"
  }
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `data.postcode` | string | Normalized postcode in standard format |
| `data.coordinates.wgs84.latitude` | float | WGS84 latitude (EPSG:4326) |
| `data.coordinates.wgs84.longitude` | float | WGS84 longitude (EPSG:4326) |
| `data.coordinates.os_grid.easting` | integer | British National Grid easting (EPSG:27700) |
| `data.coordinates.os_grid.northing` | integer | British National Grid northing (EPSG:27700) |
| `data.geography.ward` | object\|null | Ward code and name |
| `data.geography.county_electoral_division` | object\|null | County Electoral Division code and name |
| `data.geography.parish` | object\|null | Parish code and name |
| `data.geography.local_authority_district` | object\|null | Local Authority District code and name |
| `data.geography.constituency` | object\|null | Parliamentary constituency code and name |
| `data.geography.region` | object\|null | Region code and name |
| `data.geography.police_force_area` | object\|null | Police Force Area code and name |
| `data.property_count` | integer | Number of properties in this postcode |
| `data.uprns` | array\|null | Array of UPRN integers (only when `?include=uprns`) |
| `meta.api_version` | string | API version number |
| `meta.timestamp` | string | ISO 8601 timestamp of the response |

#### Geography Codes

All geography codes follow the UK Government Statistical Service (GSS) coding system:
- **Ward**: 9-character code (e.g., `E05013806`)
- **CED**: 9-character code (e.g., `E58000001`)
- **Parish**: 9-character code (e.g., `E04012690`)
- **LAD**: 9-character code (e.g., `E09000033`)
- **Constituency**: 9-character code (e.g., `E14000639`)
- **Region**: 9-character code (e.g., `E12000007`)
- **PFA**: 9-character code (e.g., `E23000001`)

Geography fields will be `null` if not applicable (e.g., County Electoral Divisions don't exist in all areas).

---

## Error Handling

All errors follow a consistent JSON format:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {}
  }
}
```

### Error Codes

#### 401 Unauthorized

**Cause**: Missing or invalid authentication token

```json
{
  "message": "Unauthenticated."
}
```

**Resolution**: Include a valid Bearer token in the `Authorization` header.

---

#### 404 Not Found

**Code**: `POSTCODE_NOT_FOUND`

**Cause**: Postcode does not exist in the database

```json
{
  "error": {
    "code": "POSTCODE_NOT_FOUND",
    "message": "No properties found for postcode ZZ99 1ZZ"
  }
}
```

**Resolution**: Verify the postcode is correct and exists in the UK.

---

#### 422 Unprocessable Entity

##### Invalid Postcode Format

**Code**: `INVALID_POSTCODE`

**Cause**: Postcode does not match UK postcode pattern

```json
{
  "error": {
    "code": "INVALID_POSTCODE",
    "message": "Invalid postcode format: does not match UK postcode pattern"
  }
}
```

**Resolution**: Provide a valid UK postcode (5-8 characters, alphanumeric).

##### Invalid Query Parameter

**Code**: `VALIDATION_ERROR`

**Cause**: Invalid value for the `include` parameter

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid request parameters",
    "details": {
      "include": [
        "The include parameter only accepts: uprns"
      ]
    }
  }
}
```

**Resolution**: Use `?include=uprns` or omit the parameter.

---

#### 500 Internal Server Error

**Code**: `INTERNAL_ERROR`

**Cause**: Unexpected server error

```json
{
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "An unexpected error occurred"
  }
}
```

**Resolution**: Contact API support if the error persists.

---

## Examples

### Basic Lookup

Request a postcode with default response (no UPRNs):

```bash
curl -X GET "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response**: 200 OK (see [Success Response](#success-response-200-ok))

---

### Lookup with UPRNs

Request a postcode with UPRN array included:

```bash
curl -X GET "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA?include=uprns" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response**: 200 OK with `uprns` array included in `data`

---

### Lowercase Postcode

The API normalizes postcodes automatically:

```bash
curl -X GET "https://dev.localelogic.uk/api/v1/postcodes/sw1a%201aa" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response**: Same as uppercase request (200 OK)

---

### Error Example: Invalid Postcode

```bash
curl -X GET "https://dev.localelogic.uk/api/v1/postcodes/INVALID" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response**: 422 Unprocessable Entity
```json
{
  "error": {
    "code": "INVALID_POSTCODE",
    "message": "Invalid postcode format: does not match UK postcode pattern"
  }
}
```

---

### Error Example: Postcode Not Found

```bash
curl -X GET "https://dev.localelogic.uk/api/v1/postcodes/ZZ991ZZ" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

**Response**: 404 Not Found
```json
{
  "error": {
    "code": "POSTCODE_NOT_FOUND",
    "message": "No properties found for postcode ZZ99 1ZZ"
  }
}
```

---

### Error Example: Missing Authentication

```bash
curl -X GET "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA" \
  -H "Accept: application/json"
```

**Response**: 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

---

## Rate Limiting

**Current Status**: No rate limiting is applied (trust-based system)

Rate limiting may be introduced in future versions. Clients should implement exponential backoff for failed requests and respect any `Retry-After` headers if returned.

### Best Practices

- **Cache responses**: Geography data rarely changes; cache by postcode
- **Batch requests**: If making multiple requests, space them appropriately
- **Handle errors gracefully**: Implement retry logic with exponential backoff
- **Monitor usage**: Track your API usage to optimize application performance

---

## Technical Notes

### Performance

- **Average response time**: < 100ms (with database indexing)
- **Database size**: 41 million property records
- **Optimization**: B-tree index on `properties.pcds` column
- **Query efficiency**: Eager loading prevents N+1 queries (8 queries per request)

### Data Sources

- **ONSUD**: Office for National Statistics Postcode Directory
- **GSS Codes**: UK Government Statistical Service geography codes
- **Coordinates**: WGS84 (EPSG:4326) and British National Grid (EPSG:27700)

### Versioning

The API uses URI versioning (e.g., `/api/v1/`). Major version changes will be announced with migration guides provided.

---

## Support

For API support, technical questions, or to report issues:

- **Email**: api-support@localelogic.uk
- **Documentation**: https://dev.localelogic.uk/docs/api
- **Issue Tracker**: Contact your account manager

---

## Changelog

### Version 1.0 (December 2024)

- Initial release
- Postcode lookup endpoint with full geography data
- Optional UPRN inclusion via query parameter
- Laravel Sanctum token authentication
- Comprehensive error handling
