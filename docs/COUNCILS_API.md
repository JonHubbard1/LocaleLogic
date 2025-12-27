# Council Endpoints API Documentation

Version 1.0 | Last Updated: December 2024

## Overview

The Council Endpoints provide access to local authority data, including lists of councils, their electoral divisions, wards, and parishes with associated postcodes.

---

## Authentication

All API requests require authentication using Laravel Sanctum tokens in the Authorization header:

```http
Authorization: Bearer YOUR_API_TOKEN
```

---

## Base URL

```
https://localelogic.uk/api/v1
```

---

## Endpoints

### 1. List All Councils

Get a list of all local authority councils with optional type filtering.

```http
GET /councils
```

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `type` | string | No | Filter by council type: `county`, `unitary`, or `district` |

#### Council Types

- **unitary**: Unitary authorities (E06%) and London boroughs (E09%)
- **district**: District councils (E07%)
- **county**: County councils (E10%) - Note: May return 0 results as ONSUD uses lowest-level LAD codes

#### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "gss_code": "E06000054",
      "name": "Wiltshire",
      "name_welsh": "",
      "type": "unitary"
    },
    {
      "gss_code": "E07000228",
      "name": "Mid Suffolk",
      "name_welsh": "",
      "type": "district"
    }
  ],
  "meta": {
    "count": 314,
    "type_filter": "all"
  }
}
```

#### Examples

**Get all councils:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/councils
```

**Get only unitary authorities:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/councils?type=unitary
```

**Get only district councils:**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/councils?type=district
```

---

### 2. Get Council Electoral Divisions

Get all electoral divisions (CEDs) for a county council with postcodes.

```http
GET /councils/{councilCode}/divisions
```

#### Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `councilCode` | string | Path | Yes | GSS code of the council (e.g., E06000054) |

#### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "gss_code": "E58000001",
      "name": "Example Division",
      "postcode_count": 45,
      "postcodes": ["SN1 1AA", "SN1 1AB", "SN1 1AC"]
    }
  ],
  "meta": {
    "council_code": "E06000054",
    "council_name": "Wiltshire",
    "division_count": 98
  }
}
```

#### Example

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/councils/E06000054/divisions
```

---

### 3. Get Council Electoral Wards

Get all electoral wards for a council with postcodes.

```http
GET /councils/{councilCode}/wards
```

#### Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `councilCode` | string | Path | Yes | GSS code of the council (e.g., E06000054) |

#### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "gss_code": "E05012345",
      "name": "Example Ward",
      "postcode_count": 120,
      "postcodes": ["SN1 1AA", "SN1 1AB", "SN1 1AC"]
    }
  ],
  "meta": {
    "council_code": "E06000054",
    "council_name": "Wiltshire",
    "council_type": "unitary",
    "ward_count": 98
  }
}
```

#### Example

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/councils/E06000054/wards
```

---

### 4. Get Council Parishes

Get all parishes for a council with postcodes.

```http
GET /councils/{councilCode}/parishes
```

#### Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `councilCode` | string | Path | Yes | GSS code of the council (e.g., E06000054) |

#### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "gss_code": "E04012345",
      "name": "Example Parish",
      "name_welsh": "",
      "postcode_count": 35,
      "postcodes": ["SN1 1AA", "SN1 1AB"]
    }
  ],
  "meta": {
    "council_code": "E06000054",
    "council_name": "Wiltshire",
    "council_type": "unitary",
    "parish_count": 245
  }
}
```

#### Example

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://localelogic.uk/api/v1/councils/E06000054/parishes
```

---

### 5. Get District Councils in County

Get all district councils within a county council area.

```http
GET /councils/{countyCode}/districts
```

#### Parameters

| Parameter | Type | Location | Required | Description |
|-----------|------|----------|----------|-------------|
| `countyCode` | string | Path | Yes | GSS code of the county council (E10xxxxx) |

**Important Note:** This endpoint returns all district councils (E07%) as there is no direct county→district relationship in the ONSUD data. Users need to filter based on their geographic knowledge.

#### Response

**Success Response (200 OK):**

```json
{
  "data": [
    {
      "gss_code": "E07000228",
      "name": "Mid Suffolk",
      "name_welsh": "",
      "county_code": "E10000029"
    }
  ],
  "meta": {
    "county_code": "E10000029",
    "county_name": "Suffolk",
    "count": 164,
    "note": "Returns all district councils. Filter based on your geographic knowledge as direct county->district relationships are not in the data."
  }
}
```

---

## Error Handling

All endpoints return standard HTTP status codes:

### Common Error Responses

**404 Not Found**
```json
{
  "error": {
    "code": "COUNCIL_NOT_FOUND",
    "message": "Council with code 'E06000999' not found"
  }
}
```

**422 Unprocessable Entity**
```json
{
  "error": {
    "code": "INVALID_COUNCIL_TYPE",
    "message": "Council 'E06000054' is not a county council"
  }
}
```

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

---

## Data Coverage

The API returns data for **314 councils** across England, Wales, and Scotland:

- **96 Unitary Authorities** (including 33 London boroughs)
- **164 District Councils**
- **54 Scottish/Welsh Councils**

All councils returned have actual property data in the system.

---

## Support

For API support or questions:
- **Email**: api-support@localelogic.uk
- **Main API Documentation**: See docs/API.md for postcode lookup endpoints
