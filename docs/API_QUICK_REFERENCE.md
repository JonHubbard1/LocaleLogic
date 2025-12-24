# Postcode Lookup API - Quick Reference

## Authentication

```bash
Authorization: Bearer YOUR_API_TOKEN
```

Generate token:
```bash
php artisan api:create-token user@email.com
```

---

## Endpoint

```
GET /api/v1/postcodes/{postcode}?include=uprns
```

| Parameter | Required | Description |
|-----------|----------|-------------|
| `postcode` | Yes | UK postcode (any format) |
| `include` | No | `uprns` to include UPRN array |

---

## Examples

### Basic Request
```bash
curl "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### With UPRNs
```bash
curl "https://dev.localelogic.uk/api/v1/postcodes/SW1A1AA?include=uprns" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Response (200 OK)

```json
{
  "data": {
    "postcode": "SW1A 1AA",
    "coordinates": {
      "wgs84": { "latitude": 51.495407, "longitude": -0.141515 },
      "os_grid": { "easting": 529000, "northing": 179000 }
    },
    "geography": {
      "ward": { "code": "E05013806", "name": "St James's" },
      "county_electoral_division": null,
      "parish": { "code": "E04012690", "name": "Melksham Without" },
      "local_authority_district": { "code": "E09000033", "name": "Westminster" },
      "constituency": { "code": "E14000639", "name": "Cities of London and Westminster" },
      "region": { "code": "E12000007", "name": "London" },
      "police_force_area": { "code": "E23000001", "name": "Metropolitan Police" }
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

---

## Error Codes

| Code | Status | Description |
|------|--------|-------------|
| - | 401 | Missing/invalid authentication token |
| `INVALID_POSTCODE` | 422 | Invalid postcode format |
| `POSTCODE_NOT_FOUND` | 404 | Postcode doesn't exist |
| `VALIDATION_ERROR` | 422 | Invalid query parameter |
| `INTERNAL_ERROR` | 500 | Server error |

### Error Response Format
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description",
    "details": {}
  }
}
```

---

## Postcode Formats Accepted

All formats are normalized automatically:
- `SW1A1AA` ✓
- `SW1A 1AA` ✓
- `sw1a 1aa` ✓
- `SW1a 1Aa` ✓

---

## Response Times

- Average: **< 100ms**
- With UPRNs: **< 150ms**

---

## Notes

- No rate limiting (trust-based)
- Tokens don't expire
- Cache responses for better performance
- Geography codes follow GSS standard
- WGS84 = EPSG:4326, OS Grid = EPSG:27700
