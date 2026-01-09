# Transportation MVP - Master API Documentation

**Version:** 1.0
**Last Updated:** January 9, 2026
**Status:** Production Ready (Phases 1-2)

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Authentication](#authentication)
3. [API Structure](#api-structure)
4. [Authentication Endpoints](#authentication-endpoints)
5. [External API (Melo ERP)](#external-api-melo-erp)
6. [Delivery Request API (ERP Integration)](#delivery-request-api-erp-integration)
7. [Driver API (Flutter App)](#driver-api-flutter-app)
8. [Notification API](#notification-api)
9. [Error Handling](#error-handling)
10. [Rate Limiting](#rate-limiting)
11. [Webhooks & Callbacks](#webhooks--callbacks)

---

## Quick Start

### Base URL
```
Production: https://transportation-app.alsabiqoon.com/api/v1
Staging:    https://staging.transportation-app.alsabiqoon.com/api/v1
Local:      http://localhost:8000/api/v1
```

### Health Check
```bash
curl https://transportation-app.alsabiqoon.com/api/v1/health
```

Response:
```json
{
  "status": "healthy"
}
```

### Authentication Methods

| Use Case | Method | Header | Example |
|----------|--------|--------|---------|
| Driver Mobile App | OAuth2 Bearer Token | `Authorization: Bearer {token}` | Flutter app users |
| ERP Integration | API Key | `X-API-Key: {api_key}` | Melo ERP, other businesses |
| External API | API Key | `X-API-Key: {api_key}` | Shop sync, waste callbacks |

---

## Authentication

### 1. OAuth2 (Driver Mobile App)

Used for driver authentication in the Flutter app.

**Flow:**
1. Driver registers or logs in with username/password
2. Server returns access token + refresh token
3. Driver uses access token in `Authorization: Bearer {token}` header
4. When token expires, driver uses refresh token to get new one

**Token Lifespan:**
- Access Token: 1 hour
- Refresh Token: 7 days

### 2. API Key Authentication (ERP Integration)

Used for business-to-business API integrations (Melo ERP, etc.).

**How to Get API Key:**
1. Contact Transportation MVP admin
2. Admin creates API key in admin panel (Filament UI)
3. API key is associated with a Business account
4. Store securely in your `.env`

**Format:**
```bash
# In your .env
TRANSPORTATION_MVP_API_KEY=your_api_key_here

# In requests
curl -H "X-API-Key: your_api_key_here" https://api.com/endpoint
```

**Scope:**
- API key is tied to ONE business account
- Can only access/modify that business's data
- Different keys for staging vs production

---

## API Structure

### Request Format

All requests use `application/json` content type.

```bash
curl -X POST https://api.com/endpoint \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"key": "value"}'
```

### Response Format

**Success Response (2xx):**
```json
{
  "data": { ... },
  "message": "Optional success message"
}
```

**List Response with Pagination:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

**Error Response:**
```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Specific validation error"]
  },
  "status": 422
}
```

### Standard Response Headers

```
Content-Type: application/json
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1641700800
```

---

## Authentication Endpoints

### Register Driver Account

**Endpoint:** `POST /auth/register`

**Authentication:** None (Public)

**Request Body:**
```json
{
  "name": "Ahmad Al-Rashid",
  "email": "ahmad@example.com",
  "phone": "+962791234567",
  "password": "secure_password_123",
  "password_confirmation": "secure_password_123"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Ahmad Al-Rashid",
    "email": "ahmad@example.com",
    "phone": "+962791234567"
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "message": "Registration successful"
}
```

### Login

**Endpoint:** `POST /auth/login`

**Authentication:** None (Public)

**Request Body:**
```json
{
  "email": "ahmad@example.com",
  "password": "secure_password_123"
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Ahmad Al-Rashid",
    "email": "ahmad@example.com",
    "phone": "+962791234567"
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Refresh Token

**Endpoint:** `POST /auth/refresh`

**Authentication:** None (Uses refresh token in request body)

**Request Body:**
```json
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Response (200 OK):**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Get Current User

**Endpoint:** `GET /auth/user`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Ahmad Al-Rashid",
    "email": "ahmad@example.com",
    "phone": "+962791234567",
    "vehicle_id": "019b9a30-f8a2-...",
    "active": true
  }
}
```

### Logout

**Endpoint:** `POST /auth/logout`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "message": "Logout successful"
}
```

---

## External API (Melo ERP)

Used by Melo ERP to sync shops and manage waste collection.

**Base Path:** `/api/external/v1`
**Authentication:** X-API-Key header
**Rate Limit:** 5-100 requests/min (varies by endpoint)

### Shop Management

#### 1. Sync Shops

Bulk synchronize shops from Melo ERP to Transportation MVP.

**Endpoint:** `POST /api/external/v1/shops/sync`

**Authentication:** Required (X-API-Key)

**Request:**
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
    },
    {
      "id": "SHOP-002",
      "name": "Fresh Goods Market",
      "address": "456 Rainbow St, Zarqa",
      "latitude": 32.0573,
      "longitude": 35.8497,
      "contact_name": "Fatima Al-Shami",
      "contact_number": "+962798765432",
      "track_waste": true,
      "status": "active"
    }
  ]
}
```

**Parameters:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| shops | array | Yes | Max 1000 shops per request |
| id | string | Yes | Unique shop ID from Melo ERP |
| name | string | Yes | Shop name (1-255 chars) |
| address | string | Yes | Full address (1-500 chars) |
| latitude | float | Yes | GPS latitude (-90 to 90) |
| longitude | float | Yes | GPS longitude (-180 to 180) |
| contact_name | string | No | Shop owner/manager name |
| contact_number | string | No | Phone number (E.164 format) |
| track_waste | boolean | No | Enable waste tracking (default: false) |
| status | string | No | "active" or "inactive" (default: "active") |

**Response (200 OK):**
```json
{
  "data": {
    "created": 2,
    "updated": 1,
    "deleted": 0,
    "total": 3
  },
  "message": "Shops synced successfully: 2 created, 1 updated, 0 deleted"
}
```

**Error Responses:**

| Status | Scenario | Example |
|--------|----------|---------|
| 401 | Invalid API key | `{"message": "Unauthorized", "status": 401}` |
| 422 | Validation error | `{"message": "Validation failed", "errors": {"shops.0.latitude": ["Latitude must be between -90 and 90"]}}` |
| 400 | Invalid coordinates | `{"message": "Invalid coordinates"}` |
| 429 | Rate limited | `{"message": "Rate limit exceeded. Try again in 60 seconds.", "retry_after": 60}` |

#### 2. List Shops

**Endpoint:** `GET /api/external/v1/shops`

**Authentication:** Required (X-API-Key)

**Query Parameters:**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| is_active | boolean | null | Filter by active status |
| track_waste | boolean | null | Filter by waste tracking enabled |
| per_page | integer | 50 | Items per page (max 100) |
| page | integer | 1 | Page number |

**Example:**
```bash
GET /api/external/v1/shops?is_active=true&track_waste=true&per_page=20&page=1
```

**Response (200 OK):**
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

#### 3. Get Shop Details

**Endpoint:** `GET /api/external/v1/shops/{externalShopId}`

**Authentication:** Required (X-API-Key)

**Response (200 OK):**
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

#### 4. Update Shop

**Endpoint:** `PUT /api/external/v1/shops/{externalShopId}`

**Authentication:** Required (X-API-Key)

**Request (All fields optional):**
```json
{
  "name": "Ahmad's Supermarket - Updated",
  "address": "123 King Abdullah St, Amman",
  "latitude": 31.9539,
  "longitude": 35.9106,
  "contact_name": "Ahmad Al-Rashid",
  "contact_number": "+962798765432",
  "track_waste": true,
  "is_active": true
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "external_shop_id": "SHOP-001",
    "name": "Ahmad's Supermarket - Updated",
    "track_waste": true,
    "contact_phone": "+962798765432",
    "updated_at": "2026-01-09T11:45:00Z"
  },
  "message": "Shop updated successfully"
}
```

#### 5. Deactivate Shop

**Endpoint:** `DELETE /api/external/v1/shops/{externalShopId}`

**Authentication:** Required (X-API-Key)

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "external_shop_id": "SHOP-001",
    "is_active": false,
    "updated_at": "2026-01-09T12:00:00Z"
  },
  "message": "Shop deactivated successfully"
}
```

### Waste Collection Management

#### 1. Get Expected Waste

Returns shops with expected waste to be collected.

**Endpoint:** `GET /api/external/v1/waste/expected`

**Authentication:** Required (X-API-Key)

**Response (200 OK):**
```json
{
  "data": [
    {
      "shop_id": "SHOP-001",
      "shop_name": "Ahmad's Supermarket",
      "collection_date": "2026-01-09",
      "items_count": 2,
      "expected_waste": [
        {
          "order_item_id": "ITEM-456",
          "product_name": "Baklava Box",
          "quantity_delivered": 10,
          "delivered_at": "2026-01-01",
          "expires_at": "2026-01-08",
          "days_expired": 1
        },
        {
          "order_item_id": "ITEM-457",
          "product_name": "Kunafa Tray",
          "quantity_delivered": 5,
          "delivered_at": "2026-01-02",
          "expires_at": "2026-01-09",
          "days_expired": 0
        }
      ]
    }
  ],
  "meta": {
    "total_shops": 1,
    "generation_date": "2026-01-09T06:00:00Z"
  }
}
```

#### 2. Set Expected Waste Dates

Tell Transportation MVP which shops have expected waste for collection.

**Endpoint:** `POST /api/external/v1/waste/expected`

**Authentication:** Required (X-API-Key)

**Request:**
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

**Response (200 OK):**
```json
{
  "data": {
    "updated": 2
  },
  "message": "Expected waste dates updated for 2 shop(s)"
}
```

---

## Delivery Request API (ERP Integration)

Used by ERP systems to submit delivery requests.

**Base Path:** `/api/v1/delivery-requests`
**Authentication:** X-API-Key header
**Purpose:** ERP submits orders, Transportation MVP optimizes and assigns to drivers

### Create Delivery Request

**Endpoint:** `POST /api/v1/delivery-requests`

**Authentication:** Required (X-API-Key)

**Request:**
```json
{
  "destinations": [
    {
      "external_id": "ORDER-001",
      "address": "123 Main St, Amman",
      "lat": 31.9539,
      "lng": 35.9106,
      "contact_name": "Ahmad Shop",
      "contact_phone": "+962791234567",
      "amount_to_collect": 125.50,
      "items": [
        {
          "order_item_id": "ITEM-001",
          "name": "Baklava Box",
          "unit_price": 35.00,
          "quantity_ordered": 2
        },
        {
          "order_item_id": "ITEM-002",
          "name": "Kunafa Tray",
          "unit_price": 27.75,
          "quantity_ordered": 2
        }
      ]
    },
    {
      "external_id": "ORDER-002",
      "address": "456 Oak Ave, Zarqa",
      "lat": 32.0573,
      "lng": 35.8497,
      "contact_name": "Fresh Goods",
      "contact_phone": "+962798765432",
      "amount_to_collect": 85.00,
      "items": [
        {
          "order_item_id": "ITEM-003",
          "name": "Baklava Box",
          "unit_price": 35.00,
          "quantity_ordered": 2
        }
      ]
    }
  ]
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
    "status": "pending",
    "total_km": 15.5,
    "estimated_cost": 7.75,
    "assigned_driver": {
      "trip_id": "019b9a30-f8a2-7a8c-9b2f-1234567890cd",
      "driver_name": "Ahmad Driver",
      "driver_phone": "+962791111111",
      "assigned_at": "2026-01-09T10:00:00Z"
    },
    "destinations": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "external_id": "ORDER-001",
        "address": "123 Main St, Amman",
        "lat": 31.9539,
        "lng": 35.9106,
        "sequence_order": 1,
        "status": "pending",
        "contact_name": "Ahmad Shop",
        "contact_phone": "+962791234567",
        "amount_to_collect": 125.50,
        "items": [
          {
            "id": "e29b41d4-5550-41d4-a716-e8400446655",
            "order_item_id": "ITEM-001",
            "name": "Baklava Box",
            "unit_price": 35.00,
            "quantity_ordered": 2,
            "quantity_delivered": 0
          }
        ]
      }
    ],
    "created_at": "2026-01-09T10:00:00Z",
    "callback_url": "https://your-erp.com/api/delivery-callback"
  },
  "message": "Delivery request created and assigned to driver"
}
```

**Error Responses:**

| Status | Scenario | Example |
|--------|----------|---------|
| 401 | Invalid API key | Invalid credentials |
| 422 | Validation error | Missing required fields |
| 400 | Invalid coordinates | Latitude/longitude out of range |

### List Delivery Requests

**Endpoint:** `GET /api/v1/delivery-requests`

**Authentication:** Required (X-API-Key)

**Query Parameters:**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| status | string | null | Filter by status: pending, accepted, in_progress, completed |
| per_page | integer | 15 | Items per page |
| page | integer | 1 | Page number |

**Response:**
```json
{
  "data": [
    {
      "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
      "status": "in_progress",
      "total_km": 15.5,
      "estimated_cost": 7.75,
      "destinations_count": 2,
      "completed_count": 0,
      "created_at": "2026-01-09T10:00:00Z",
      "updated_at": "2026-01-09T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Get Delivery Request Details

**Endpoint:** `GET /api/v1/delivery-requests/{deliveryRequest}`

**Authentication:** Required (X-API-Key)

**Response:** Same as Create response above

### Get Route for Delivery Request

**Endpoint:** `GET /api/v1/delivery-requests/{deliveryRequest}/route`

**Authentication:** Required (X-API-Key)

**Response (200 OK):**
```json
{
  "data": {
    "polyline": "encoded_polyline_string...",
    "waypoint_order": [0, 1, 2],
    "total_distance_km": 15.5,
    "total_duration_minutes": 45,
    "destinations": [
      {
        "sequence_order": 1,
        "external_id": "ORDER-001",
        "lat": 31.9539,
        "lng": 35.9106,
        "address": "123 Main St, Amman"
      }
    ]
  }
}
```

### Cancel Delivery Request

**Endpoint:** `POST /api/v1/delivery-requests/{deliveryRequest}/cancel`

**Authentication:** Required (X-API-Key)

**Request:**
```json
{
  "reason": "Order cancelled by customer"
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
    "status": "cancelled",
    "reason": "Order cancelled by customer",
    "cancelled_at": "2026-01-09T10:45:00Z"
  },
  "message": "Delivery request cancelled successfully"
}
```

---

## Driver API (Flutter App)

Used by Flutter mobile app for drivers to manage their trips and deliveries.

**Base Path:** `/api/v1/driver`
**Authentication:** Bearer Token (OAuth2)
**Purpose:** Real-time trip management, delivery tracking, waste collection

### Profile Management

#### Get Driver Profile

**Endpoint:** `GET /api/v1/driver/profile`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Ahmad Al-Rashid",
    "email": "ahmad@example.com",
    "phone": "+962791234567",
    "photo_url": "https://storage.example.com/drivers/photo.jpg",
    "vehicle": {
      "id": "019b9a30-f8a2-7a8c-9b2f-1234567890ef",
      "make": "Volkswagen",
      "model": "Caddy",
      "year": 2019,
      "plate_number": "AB12345",
      "total_km_driven": 45230.50
    },
    "status": "active",
    "created_at": "2025-12-01T10:00:00Z"
  }
}
```

#### Update Driver Profile

**Endpoint:** `PUT /api/v1/driver/profile`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "name": "Ahmad Al-Rashid",
  "phone": "+962791234567",
  "email": "ahmad@example.com"
}
```

**Response:** Same as Get Profile above

#### Upload Profile Photo

**Endpoint:** `POST /api/v1/driver/profile/photo`

**Authentication:** Required (Bearer Token)

**Request:** Multipart form data
```bash
curl -X POST /api/v1/driver/profile/photo \
  -H "Authorization: Bearer {token}" \
  -F "photo=@/path/to/photo.jpg"
```

**Response (200 OK):**
```json
{
  "data": {
    "photo_url": "https://storage.example.com/drivers/photo-uuid.jpg"
  },
  "message": "Photo uploaded successfully"
}
```

#### Update Vehicle Odometer

**Endpoint:** `PUT /api/v1/driver/vehicle/odometer`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "odometer_reading": 45250.75
}
```

**Response (200 OK):**
```json
{
  "data": {
    "total_km_driven": 45250.75,
    "updated_at": "2026-01-09T10:30:00Z"
  },
  "message": "Odometer updated successfully"
}
```

#### Get Driver Statistics

**Endpoint:** `GET /api/v1/driver/stats`

**Authentication:** Required (Bearer Token)

**Query Parameters:**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| period | string | today | "today", "this_month", "all_time" |

**Example:**
```bash
GET /api/v1/driver/stats?period=this_month
```

**Response (200 OK):**
```json
{
  "data": {
    "period": "this_month",
    "stats": {
      "total_trips": 25,
      "total_destinations": 48,
      "completed_destinations": 46,
      "total_km": 342.50,
      "total_earnings": 171.25,
      "completion_rate": 95.83,
      "average_delivery_time_minutes": 8
    },
    "vehicle": {
      "acquisition_km": 25000.00,
      "total_km_driven": 45230.50,
      "app_tracked_km": 342.50
    }
  }
}
```

#### Get Trip History

**Endpoint:** `GET /api/v1/driver/trips/history`

**Authentication:** Required (Bearer Token)

**Query Parameters:**

| Param | Type | Notes |
|-------|------|-------|
| from | date | Filter from date (YYYY-MM-DD) |
| to | date | Filter to date (YYYY-MM-DD) |
| status | string | Filter by status |
| per_page | integer | Pagination size (default: 15) |
| page | integer | Page number |

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
      "business_name": "Melo Group",
      "status": "completed",
      "destinations_count": 5,
      "completed_count": 5,
      "total_km": 22.5,
      "total_earnings": 11.25,
      "started_at": "2026-01-09T08:00:00Z",
      "completed_at": "2026-01-09T12:00:00Z",
      "duration_minutes": 240
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

### Trip Management

#### Get Today's Trips

**Endpoint:** `GET /api/v1/driver/trips/today`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
      "business_name": "Melo Group",
      "status": "pending",
      "destinations_count": 5,
      "completed_count": 0,
      "total_km": 22.5,
      "estimated_cost": 11.25,
      "start_time": null,
      "assigned_at": "2026-01-09T06:00:00Z"
    }
  ]
}
```

#### Get Trip Details

**Endpoint:** `GET /api/v1/driver/trips/{trip}`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
    "business_name": "Melo Group",
    "status": "in_progress",
    "total_km": 22.5,
    "estimated_cost": 11.25,
    "polyline": "encoded_polyline...",
    "destinations": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "external_id": "ORDER-001",
        "address": "123 Main St, Amman",
        "lat": 31.9539,
        "lng": 35.9106,
        "sequence_order": 1,
        "status": "arrived",
        "contact_name": "Ahmad Shop",
        "contact_phone": "+962791234567",
        "amount_to_collect": 125.50,
        "arrived_at": "2026-01-09T08:30:00Z",
        "items": [
          {
            "id": "e29b41d4-5550-41d4-a716-e8400446655",
            "order_item_id": "ITEM-001",
            "name": "Baklava Box",
            "unit_price": 35.00,
            "quantity_ordered": 2,
            "quantity_delivered": 2
          }
        ]
      }
    ],
    "started_at": "2026-01-09T08:00:00Z"
  }
}
```

#### Start Trip

**Endpoint:** `POST /api/v1/driver/trips/{trip}/start`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "odometer_reading": 45230.00,
  "initial_coordinates": {
    "lat": 31.9539,
    "lng": 35.9106
  }
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
    "status": "in_progress",
    "started_at": "2026-01-09T08:00:00Z"
  },
  "message": "Trip started successfully"
}
```

#### Complete Trip

**Endpoint:** `POST /api/v1/driver/trips/{trip}/complete`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "odometer_reading": 45252.50,
  "final_coordinates": {
    "lat": 31.9539,
    "lng": 35.9106
  },
  "notes": "All deliveries completed successfully"
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
    "status": "completed",
    "total_km": 22.50,
    "completed_at": "2026-01-09T12:00:00Z"
  },
  "message": "Trip completed successfully"
}
```

### Destination Management

#### Arrive at Destination

**Endpoint:** `POST /api/v1/driver/trips/{trip}/destinations/{destination}/arrive`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "arrived_at": "2026-01-09T08:30:00Z",
  "current_coordinates": {
    "lat": 31.9539,
    "lng": 35.9106
  },
  "notes": "Arrived at shop"
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "arrived",
    "arrived_at": "2026-01-09T08:30:00Z"
  },
  "message": "Arrival confirmed"
}
```

#### Complete Destination Delivery

**Endpoint:** `POST /api/v1/driver/trips/{trip}/destinations/{destination}/complete`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "completed_at": "2026-01-09T08:45:00Z",
  "signature_base64": "base64_encoded_signature_image",
  "items": [
    {
      "order_item_id": "ITEM-001",
      "quantity_delivered": 2,
      "quantity_received": 2,
      "notes": null
    },
    {
      "order_item_id": "ITEM-002",
      "quantity_delivered": 2,
      "quantity_received": 1,
      "notes": "Customer refused 1 unit"
    }
  ],
  "amount_collected": 125.50,
  "payment_method": "cash",
  "notes": "Delivery completed"
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "completed_at": "2026-01-09T08:45:00Z",
    "amount_collected": 125.50
  },
  "message": "Destination marked as completed. Callback sent to business."
}
```

#### Fail Destination

**Endpoint:** `POST /api/v1/driver/trips/{trip}/destinations/{destination}/fail`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "reason": "shop_closed",
  "notes": "Shop was closed, will try again tomorrow",
  "completed_at": "2026-01-09T09:00:00Z"
}
```

**Valid Reasons:**
- `shop_closed`
- `wrong_address`
- `customer_not_available`
- `damaged_items`
- `other`

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "failed",
    "reason": "shop_closed",
    "failed_at": "2026-01-09T09:00:00Z"
  },
  "message": "Destination marked as failed"
}
```

#### Get Navigation URL

**Endpoint:** `GET /api/v1/driver/trips/{trip}/destinations/{destination}/navigate`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "google_maps_url": "https://www.google.com/maps/dir/?api=1&destination=31.9539,35.9106&travelmode=driving"
  }
}
```

Use this URL to open Google Maps app on driver's device.

### Waste Collection

#### Get Expected Waste for Shop

**Endpoint:** `GET /api/v1/driver/shops/{shop}/waste-expected`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "shop": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "external_id": "SHOP-001",
      "name": "Ahmad's Supermarket",
      "address": "123 King Abdullah St, Amman",
      "contact_phone": "+962791234567"
    },
    "waste_items": [
      {
        "id": "e29b41d4-5550-41d4-a716-e8400446655",
        "order_item_id": "ITEM-456",
        "product_name": "Baklava Box",
        "quantity_delivered": 10,
        "delivered_at": "2026-01-01",
        "expires_at": "2026-01-08",
        "is_expired": true,
        "days_expired": 1
      }
    ],
    "total_expected_items": 1
  }
}
```

#### Log Waste Collection

**Endpoint:** `POST /api/v1/driver/trips/{trip}/shops/{shop}/waste-collected`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "waste_items": [
    {
      "waste_item_id": "e29b41d4-5550-41d4-a716-e8400446655",
      "pieces_waste": 3,
      "notes": "Packaging damaged"
    },
    {
      "waste_item_id": "f40c52e5-6661-52e5-b827-f9511557766a",
      "pieces_waste": 0,
      "notes": null
    }
  ],
  "driver_notes": "Waste collection completed at Ahmad's Supermarket"
}
```

**Response (200 OK):**
```json
{
  "data": {
    "id": "019b9a30-f892-7a8c-9b2f-1234567890xy",
    "shop_id": "550e8400-e29b-41d4-a716-446655440000",
    "collection_date": "2026-01-09",
    "collected_at": "2026-01-09T14:30:00Z",
    "items_collected": 2,
    "total_waste": 3,
    "total_sold": 7
  },
  "message": "Waste collection logged successfully. Callback sent to Melo ERP."
}
```

---

## Notification API

Real-time push notifications via Firebase Cloud Messaging (FCM).

**Base Path:** `/api/v1/driver/notifications`
**Authentication:** Bearer Token (OAuth2)
**Purpose:** Notify drivers of trip assignments, payments, actions

### Register FCM Token

Called by Flutter app on startup and when token refreshes.

**Endpoint:** `POST /api/v1/driver/notifications/register-token`

**Authentication:** Required (Bearer Token)

**Request:**
```json
{
  "fcm_token": "eWQATGLcjUU:APA91bFxOxABc..."
}
```

**Response (200 OK):**
```json
{
  "data": {
    "fcm_token": "eWQATGLcjUU:APA91bFxOxABc...",
    "updated_at": "2026-01-09T10:00:00Z"
  },
  "message": "FCM token registered successfully"
}
```

### Get Notifications

**Endpoint:** `GET /api/v1/driver/notifications`

**Authentication:** Required (Bearer Token)

**Query Parameters:**

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| per_page | integer | 20 | Items per page |
| page | integer | 1 | Page number |

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "trip_assigned",
      "title": "New Trip Assigned",
      "body": "You have been assigned a new delivery trip with 5 stops",
      "data": {
        "trip_id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
        "destinations_count": 5,
        "total_km": 22.5
      },
      "status": "sent",
      "read_at": null,
      "created_at": "2026-01-09T07:00:00Z",
      "sent_at": "2026-01-09T07:00:05Z"
    },
    {
      "id": "660e8500-f30c-52e5-b827-557666551111",
      "type": "payment_received",
      "title": "Payment Received",
      "body": "You received 150 JOD from Melo Group",
      "data": {
        "amount": 150.00,
        "currency": "JOD"
      },
      "status": "sent",
      "read_at": "2026-01-09T07:15:00Z",
      "created_at": "2026-01-09T07:10:00Z",
      "sent_at": "2026-01-09T07:10:05Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 2
  }
}
```

### Get Unread Count

**Endpoint:** `GET /api/v1/driver/notifications/unread-count`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "unread_count": 5
  }
}
```

### Get Unread Notifications

**Endpoint:** `GET /api/v1/driver/notifications/unread`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "trip_assigned",
      "title": "New Trip Assigned",
      "body": "You have been assigned a new delivery trip with 5 stops",
      "status": "sent",
      "read_at": null,
      "created_at": "2026-01-09T07:00:00Z"
    }
  ]
}
```

### Mark Notification as Read

**Endpoint:** `PATCH /api/v1/driver/notifications/{notification}/read`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "read_at": "2026-01-09T10:30:00Z"
  },
  "message": "Notification marked as read"
}
```

### Mark Notification as Unread

**Endpoint:** `PATCH /api/v1/driver/notifications/{notification}/unread`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "read_at": null
  },
  "message": "Notification marked as unread"
}
```

### Mark All as Read

**Endpoint:** `PATCH /api/v1/driver/notifications/mark-all-read`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "message": "All notifications marked as read"
}
```

### Delete Notification

**Endpoint:** `DELETE /api/v1/driver/notifications/{notification}`

**Authentication:** Required (Bearer Token)

**Response (200 OK):**
```json
{
  "message": "Notification deleted"
}
```

---

## Error Handling

### Error Response Format

```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Specific validation error"],
    "another_field": ["First error", "Second error"]
  },
  "status": 422
}
```

### Standard HTTP Status Codes

| Status | Meaning | Retry? | Example |
|--------|---------|--------|---------|
| 200 | Success | No | Request completed successfully |
| 201 | Created | No | Resource created |
| 202 | Accepted | No | Request accepted for processing |
| 400 | Bad Request | No | Malformed request (fix and retry) |
| 401 | Unauthorized | No | Missing/invalid credentials |
| 403 | Forbidden | No | Access denied (not your resource) |
| 404 | Not Found | No | Resource doesn't exist |
| 422 | Validation Error | No | Invalid data (fix and retry) |
| 429 | Rate Limited | Yes | Too many requests (wait and retry) |
| 500 | Server Error | Yes | Internal error (exponential backoff) |
| 502 | Bad Gateway | Yes | Service temporarily unavailable |
| 503 | Service Unavailable | Yes | Server maintenance |
| 504 | Gateway Timeout | Yes | Request timeout |

### Common Error Examples

**Authentication Error (401):**
```json
{
  "message": "Unauthorized",
  "status": 401
}
```

**Validation Error (422):**
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["Email is required", "Email must be valid"],
    "password": ["Password must be at least 8 characters"]
  },
  "status": 422
}
```

**Authorization Error (403):**
```json
{
  "message": "You do not have permission to access this resource",
  "status": 403
}
```

**Rate Limited (429):**
```json
{
  "message": "Rate limit exceeded. Try again in 60 seconds.",
  "retry_after": 60,
  "status": 429
}
```

---

## Rate Limiting

### Rate Limit Headers

All responses include rate limit information:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1641700800
```

- **Limit:** Maximum requests allowed in window
- **Remaining:** Requests left in current window
- **Reset:** Unix timestamp when limit resets

### Rate Limits by Endpoint

| Endpoint Category | Limit | Window | Notes |
|---|---|---|---|
| Authentication | 10 | 1 minute | Per email/phone |
| Delivery Requests | 5 | 1 minute | Per business |
| Shop Sync | 5 | 1 minute | Per business |
| Driver Trips | 100 | 1 minute | Per driver |
| Notifications | 100 | 1 minute | Per driver |
| Waste Collection | 10 | 1 minute | Per driver |

### Handling Rate Limits

When you receive 429:

```bash
# 1. Read the X-RateLimit-Reset header
reset_timestamp=$(curl -I https://api.com/endpoint | grep X-RateLimit-Reset)

# 2. Wait until reset
sleep $(($reset_timestamp - $(date +%s)))

# 3. Retry the request
curl https://api.com/endpoint
```

---

## Webhooks & Callbacks

### Delivery Completion Callback

When driver completes a destination, Transportation MVP sends callback to your business webhook URL.

**Sent By:** Transportation MVP
**Sent To:** Business's `callback_url` field
**Content-Type:** `application/json`
**Signature:** `X-Webhook-Signature` (HMAC-SHA256)

**Callback Payload:**
```json
{
  "event": "delivery_completed",
  "delivery_request_id": "019b9a30-f892-7a8c-9b2f-1234567890ab",
  "destination": {
    "external_id": "ORDER-001",
    "address": "123 Main St, Amman",
    "contact_name": "Ahmad Shop"
  },
  "completed_at": "2026-01-09T08:45:00Z",
  "amount_collected": 125.50,
  "items": [
    {
      "order_item_id": "ITEM-001",
      "quantity_ordered": 2,
      "quantity_delivered": 2,
      "notes": null
    },
    {
      "order_item_id": "ITEM-002",
      "quantity_ordered": 2,
      "quantity_delivered": 1,
      "notes": "Customer refused 1 unit"
    }
  ]
}
```

### Waste Collection Callback

When driver logs waste, Transportation MVP sends callback to Melo ERP.

**Sent By:** Transportation MVP
**Sent To:** Melo ERP's `/api/v1/waste/callback`
**Content-Type:** `application/json`
**Signature:** `X-Webhook-Signature` (HMAC-SHA256)

**Callback Payload:**
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

### Verify Webhook Signature

**Signature Method:** HMAC-SHA256

**PHP Example:**
```php
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');
$secret = env('TRANSPORTATION_MVP_WEBHOOK_SECRET');

$expectedSignature = hash_hmac('sha256', $body, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}
```

**Node.js Example:**
```javascript
const crypto = require('crypto');

const signature = req.headers['x-webhook-signature'];
const body = req.rawBody; // Must preserve raw body
const secret = process.env.TRANSPORTATION_MVP_WEBHOOK_SECRET;

const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(body)
    .digest('hex');

if (!crypto.timingSafeEqual(expectedSignature, signature)) {
    res.status(401).json({ error: 'Invalid signature' });
    return;
}
```

### Webhook Retry Strategy

If your webhook returns non-2xx status:

| Attempt | Delay | Total |
|---------|-------|-------|
| 1 | Immediate | 0s |
| 2 | 10 seconds | 10s |
| 3 | 30 seconds | 40s |
| 4 | 1 minute | 100s |
| 5 | 2 minutes | 220s |

After 5 attempts, webhook is abandoned and logged for manual review.

### Webhook Best Practices

1. **Return 2xx status immediately** - Don't process synchronously
2. **Handle duplicates** - Same callback may arrive twice
3. **Log everything** - For debugging and audit trail
4. **Verify signature** - Always validate HMAC-SHA256
5. **Implement idempotency** - Use unique keys to prevent duplicate processing
6. **Send alerts** - Notify ops if critical callbacks fail

---

## Data Types

### Notification Object

```typescript
{
  id: string (UUID),
  type: "trip_assigned" | "trip_reassigned" | "payment_received" | "action_required",
  title: string,
  body: string,
  data: object (JSON),
  status: "pending" | "sent" | "failed",
  read_at: string (ISO8601) | null,
  created_at: string (ISO8601),
  sent_at: string (ISO8601)
}
```

### Shop Object

```typescript
{
  id: string (UUID),
  external_shop_id: string,
  name: string,
  address: string,
  lat: number,
  lng: number,
  contact_name?: string,
  contact_phone?: string,
  track_waste: boolean,
  is_active: boolean,
  last_synced_at?: string (ISO8601),
  created_at: string (ISO8601),
  updated_at: string (ISO8601)
}
```

### Waste Collection Item

```typescript
{
  id: string (UUID),
  order_item_id: string,
  product_name: string,
  quantity_delivered: integer,
  delivered_at: string (date),
  expires_at: string (date),
  pieces_waste: integer,
  pieces_sold: integer (calculated),
  notes?: string,
  is_expired: boolean,
  days_expired: integer
}
```

---

## Support & Resources

**Documentation:** https://docs.transportation-mvp.app
**Email:** api-support@transportation-mvp.app
**Slack:** #integration-support
**Status Page:** https://status.transportation-mvp.app

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-09 | Initial release (Phases 1-2) |
| 1.1 | TBD | Offline support API (Phase 3) |
| 2.0 | TBD | Advanced analytics (Future) |
