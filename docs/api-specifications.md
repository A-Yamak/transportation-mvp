# Transportation MVP External API Specifications

**Version 1.0** | **Last Updated: January 9, 2026**

---

## Table of Contents

1. [Authentication](#authentication)
2. [Shop Management](#shop-management)
3. [Waste Collection](#waste-collection)
4. [Error Handling](#error-handling)
5. [Rate Limiting](#rate-limiting)

---

## Authentication

All requests to `/api/external/v1` endpoints require API key authentication.

### Headers

```
X-API-Key: {api_key}
Content-Type: application/json
```

### Error Response (Invalid Key)

```json
{
  "message": "Unauthorized",
  "status": 401
}
```

---

## Shop Management

### 1. Sync Shops

**Endpoint:** `POST /api/external/v1/shops/sync`

Bulk synchronize shops from Melo ERP. Creates new shops and updates existing ones.

**Rate Limit:** 5 requests/minute

**Request**

```json
{
  "shops": [
    {
      "id": "SHOP-001",
      "name": "Ahmad's Supermarket",
      "address": "123 King Abdullah St, Amman",
      "latitude": 31.9539,
      "longitude": 35.9106,
      "contact_name": "Ahmad Al-Rashid",
      "contact_number": "+962791234567",
      "track_waste": true,
      "status": "active"
    }
  ]
}
```

**Parameters**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| shops | array | Yes | Max 1000 shops per request |
| id | string | Yes | Unique shop ID in Melo ERP |
| name | string | Yes | Shop name (1-255 chars) |
| address | string | Yes | Full address (1-500 chars) |
| latitude | float | Yes | Valid GPS latitude (-90 to 90) |
| longitude | float | Yes | Valid GPS longitude (-180 to 180) |
| contact_name | string | No | Shop owner/manager name |
| contact_number | string | No | Phone number (valid E.164 format recommended) |
| track_waste | boolean | No | Enable waste tracking (default: false) |
| status | string | No | "active" or "inactive" (default: "active") |

**Response (200 OK)**

```json
{
  "data": {
    "created": 5,
    "updated": 3,
    "deleted": 0,
    "total": 8
  },
  "message": "Shops synced successfully"
}
```

**Error Responses**

```json
{
  "message": "Validation failed",
  "errors": {
    "shops.0.latitude": ["Latitude must be between -90 and 90"],
    "shops.1.name": ["Name field is required"]
  },
  "status": 422
}
```

---

### 2. List Shops

**Endpoint:** `GET /api/external/v1/shops`

Retrieve all shops for the authenticated business.

**Query Parameters**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| is_active | boolean | null | Filter by status |
| track_waste | boolean | null | Filter by waste tracking enabled |
| per_page | integer | 50 | Items per page (max 100) |
| page | integer | 1 | Page number |

**Response (200 OK)**

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "external_shop_id": "SHOP-001",
      "name": "Ahmad's Supermarket",
      "address": "123 King Abdullah St, Amman",
      "lat": 31.9539,
      "lng": 35.9106,
      "contact_name": "Ahmad Al-Rashid",
      "contact_phone": "+962791234567",
      "track_waste": true,
      "is_active": true,
      "last_synced_at": "2026-01-09T10:30:00Z",
      "created_at": "2026-01-08T14:22:00Z",
      "updated_at": "2026-01-09T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1
  }
}
```

---

### 3. Get Shop Details

**Endpoint:** `GET /api/external/v1/shops/{externalShopId}`

Retrieve details of a specific shop by external ID.

**Parameters**

| Param | Type | Notes |
|-------|------|-------|
| externalShopId | string | Shop ID from Melo ERP (path parameter) |

**Response (200 OK)**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "external_shop_id": "SHOP-001",
    "name": "Ahmad's Supermarket",
    "address": "123 King Abdullah St, Amman",
    "lat": 31.9539,
    "lng": 35.9106,
    "contact_name": "Ahmad Al-Rashid",
    "contact_phone": "+962791234567",
    "track_waste": true,
    "is_active": true,
    "last_synced_at": "2026-01-09T10:30:00Z",
    "created_at": "2026-01-08T14:22:00Z",
    "updated_at": "2026-01-09T10:30:00Z"
  }
}
```

**Error Response (404 Not Found)**

```json
{
  "message": "Shop not found",
  "status": 404
}
```

---

### 4. Update Shop

**Endpoint:** `PUT /api/external/v1/shops/{externalShopId}`

Update a shop's details. All fields are optional.

**Parameters**

| Field | Type | Notes |
|-------|------|-------|
| name | string | Updated shop name |
| address | string | Updated address |
| latitude | float | Updated latitude |
| longitude | float | Updated longitude |
| contact_name | string | Updated contact name |
| contact_number | string | Updated phone number |
| track_waste | boolean | Enable/disable waste tracking |
| is_active | boolean | Activate/deactivate shop |

**Request**

```json
{
  "track_waste": true,
  "contact_number": "+962798765432"
}
```

**Response (200 OK)**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "external_shop_id": "SHOP-001",
    "name": "Ahmad's Supermarket",
    "track_waste": true,
    "contact_phone": "+962798765432",
    "updated_at": "2026-01-09T11:45:00Z"
  },
  "message": "Shop updated successfully"
}
```

---

## Waste Collection

### 1. Get Expected Waste

**Endpoint:** `GET /api/external/v1/waste/expected`

Retrieve shops with expected waste to be collected. Used by Transportation MVP to plan waste collection routes.

**Response (200 OK)**

```json
{
  "data": [
    {
      "shop_id": "SHOP-001",
      "shop_name": "Ahmad's Supermarket",
      "expected_waste": [
        {
          "order_item_id": "ITEM-456",
          "product_name": "Baklava Box",
          "quantity_delivered": 10,
          "delivered_at": "2026-01-01",
          "expires_at": "2026-01-08",
          "waste_date": "2026-01-01",
          "notes": null
        }
      ],
      "collection_date": "2026-01-09"
    }
  ],
  "meta": {
    "total_shops": 1,
    "generation_date": "2026-01-09T06:00:00Z"
  }
}
```

---

### 2. Set Expected Waste Dates

**Endpoint:** `POST /api/external/v1/waste/expected`

Tell Transportation MVP which shops have expected waste for a specific date.

**Request**

```json
{
  "shops": [
    {
      "external_shop_id": "SHOP-001",
      "expected_waste_date": "2026-01-10"
    },
    {
      "external_shop_id": "SHOP-002",
      "expected_waste_date": "2026-01-10"
    }
  ]
}
```

**Parameters**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| shops | array | Yes | List of shops with expected waste |
| external_shop_id | string | Yes | Shop ID from Melo ERP |
| expected_waste_date | date | Yes | Date (YYYY-MM-DD format) when waste is expected |

**Response (200 OK)**

```json
{
  "data": {
    "updated": 2
  },
  "message": "Expected waste dates updated for 2 shop(s)"
}
```

---

## Callbacks (Inbound from Transportation MVP)

### Waste Collection Callback

**Endpoint (Your Implementation):** `POST /api/v1/waste/callback`

Transportation MVP will POST waste collection data to this endpoint after drivers log waste.

**Headers from Transportation MVP**

```
Content-Type: application/json
X-Webhook-Signature: {hmac_sha256_signature}
```

**Request Body**

```json
{
  "event": "waste_collected",
  "shop_id": "SHOP-001",
  "shop_name": "Ahmad's Supermarket",
  "collection_date": "2026-01-09",
  "collected_at": "2026-01-09T14:30:00Z",
  "collected_by_user_id": 789,
  "waste_items": [
    {
      "order_item_id": "ITEM-456",
      "product_name": "Baklava Box",
      "quantity_delivered": 10,
      "pieces_returned": 3,
      "pieces_sold": 7,
      "waste_date": "2026-01-01",
      "notes": "Packaging damaged"
    }
  ]
}
```

**Parameters**

| Field | Type | Notes |
|-------|------|-------|
| event | string | Always "waste_collected" |
| shop_id | string | External shop ID from Melo ERP |
| shop_name | string | Shop name for reference |
| collection_date | date | Date waste was collected (YYYY-MM-DD) |
| collected_at | datetime | ISO8601 timestamp when collected |
| collected_by_user_id | integer | ID of driver who collected waste |
| waste_items | array | Items with waste quantities |
| order_item_id | string | External item ID from Melo ERP |
| product_name | string | Product name |
| quantity_delivered | integer | Units delivered to shop |
| pieces_returned | integer | Units returned as waste |
| pieces_sold | integer | Units sold (delivered - returned) |
| waste_date | date | Date items were delivered |
| notes | string\|null | Driver notes about waste |

**Required Response**

```json
{
  "status": "success"
}
```

**Status Codes**

| Code | Meaning |
|------|---------|
| 200 | Success (webhook processed) |
| 201 | Created (new record created) |
| 202 | Accepted (queued for processing) |
| 400 | Bad request (invalid data) |
| 401 | Unauthorized (invalid signature) |
| 500 | Server error (will retry) |

---

## Error Handling

### Standard Error Format

```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Specific error for field"]
  },
  "status": 422
}
```

### Common Status Codes

| Status | Meaning | Retry? |
|--------|---------|--------|
| 200 | Success | No |
| 201 | Created | No |
| 400 | Bad request | No |
| 401 | Unauthorized | No |
| 422 | Validation error | No |
| 429 | Rate limited | Yes (wait 60s) |
| 500 | Server error | Yes (exponential backoff) |
| 502/503 | Service unavailable | Yes (exponential backoff) |
| 504 | Gateway timeout | Yes (exponential backoff) |

---

## Rate Limiting

### Limits per Business

| Endpoint | Limit | Window |
|----------|-------|--------|
| POST /shops/sync | 5 | 1 minute |
| GET /shops | 100 | 1 minute |
| GET /waste/expected | 100 | 1 minute |
| POST /waste/expected | 10 | 1 minute |

### Rate Limit Headers

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1641700800
```

### Rate Limited Response (429)

```json
{
  "message": "Rate limit exceeded. Try again in 60 seconds.",
  "status": 429,
  "retry_after": 60
}
```

---

## Data Types

### Shop Object

```typescript
{
  id: string (UUID),                      // Internal ID
  external_shop_id: string,               // ID from Melo ERP
  name: string,                           // Shop name
  address: string,                        // Full address
  lat: number,                            // Latitude (-90 to 90)
  lng: number,                            // Longitude (-180 to 180)
  contact_name?: string,                  // Shop owner/manager
  contact_phone?: string,                 // Phone number
  track_waste: boolean,                   // Waste tracking enabled
  is_active: boolean,                     // Shop status
  last_synced_at?: string (ISO8601),     // Last sync timestamp
  created_at: string (ISO8601),           // Creation timestamp
  updated_at: string (ISO8601)            // Last update timestamp
}
```

### Waste Collection Item

```typescript
{
  order_item_id: string,                  // Item ID from Melo ERP
  product_name: string,                   // Product name
  quantity_delivered: integer,            // Units delivered
  delivered_at: string (date),            // Delivery date
  expires_at: string (date),              // Expiration date
  pieces_waste: integer,                  // Units wasted
  pieces_sold: integer,                   // Units sold (calculated)
  notes?: string,                         // Optional notes
  is_expired: boolean,                    // Whether expired
  waste_percentage: number                // Waste % (calculated)
}
```

---

## Pagination

List endpoints support cursor-based pagination:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100,
    "from": 1,
    "to": 20
  },
  "links": {
    "first": "https://.../shops?page=1",
    "last": "https://.../shops?page=5",
    "next": "https://.../shops?page=2"
  }
}
```

---

## Timestamps

All timestamps use ISO 8601 format with UTC timezone:

```
2026-01-09T14:30:00Z
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-09 | Initial release |

---

## Support

For API questions or issues:
- Email: api-support@transportation-mvp.app
- Slack: #integration-support
- Documentation: https://docs.transportation-mvp.app
