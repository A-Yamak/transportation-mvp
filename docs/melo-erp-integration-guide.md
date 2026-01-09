# Melo ERP Integration Guide

**Transportation MVP - Waste Tracking & Shop Management**

This guide explains how to integrate the Transportation MVP with Melo ERP for shop synchronization and waste collection tracking.

---

## Overview

The Transportation MVP now supports:
- **Shop Synchronization**: Pull shop locations from Melo ERP
- **Waste Collection Tracking**: Drivers log waste items (expired products) from shops
- **Automated Callbacks**: Waste data is automatically sent back to Melo ERP after collection
- **Expected Waste Routing**: Daily updates of shops with expected waste for collection

### Integration Architecture

```
Melo ERP                          Transportation MVP
   |                                     |
   |-- GET /api/v1/shops -------->      |
   |<----- [shop list] -----------      |
   |                                     |
   |-- POST /api/v1/waste/expected ----> |
   |<----- [waste dates] --------        |
   |                                     |
   |                                [Driver logs waste]
   |                                     |
   |<---- POST /api/v1/waste/callback-- |
```

---

## Phase 1: Setup API Keys

### Step 1: Create API Key in Transportation MVP

Contact the Transportation MVP admin to create an API key for Melo ERP:

```bash
# Admin action (run in transportation MVP)
php artisan tinker
>>> $business = Business::where('name', 'Melo Group')->first();
>>> $key = $business->api_keys()->create(['name' => 'Melo ERP Integration']);
>>> echo $key->key;
```

**Store this API key securely** - you'll use it for all requests.

### Step 2: Add API Key to Headers

All requests to Transportation MVP must include the API key:

```bash
X-API-Key: your_api_key_here
```

---

## Phase 2: Shop Synchronization (Pull Model)

### Endpoint: POST `/api/external/v1/shops/sync`

Synchronizes shops from Melo ERP to Transportation MVP. Call this endpoint whenever shops are created/updated in Melo ERP.

**Frequency Recommendations:**
- **Initial**: When onboarding Melo ERP
- **Scheduled**: Daily at 2 AM (before drivers start work)
- **On-Demand**: When new shops are created

### Request Body

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

### Response

```json
{
  "data": {
    "created": 2,
    "updated": 0,
    "deleted": 0,
    "total": 2
  },
  "message": "Shops synced successfully"
}
```

### Error Cases

| Status | Scenario | Action |
|--------|----------|--------|
| 401 | Invalid API key | Verify X-API-Key header |
| 422 | Missing required fields | Check id, name, address, coordinates |
| 400 | Invalid coordinates | Ensure latitude and longitude are valid floats |

### Integration Tips

1. **Idempotent**: Can safely call multiple times with same shops
2. **Upsert Logic**: If shop with same `id` exists, update it; otherwise create
3. **Contact Info**: Store contact_name and contact_number for driver reference
4. **Tracking Flag**: Set `track_waste: true` for shops where waste collection is enabled
5. **Status Filtering**: Only activate shops where `status: active`

---

## Phase 3: Expected Waste Management

### Step 1: Get Expected Waste (Optional Pre-View)

**Endpoint:** `GET /api/external/v1/waste/expected`

**Purpose**: Check which shops have expected waste pending collection

**Response Example:**

```json
{
  "data": [
    {
      "shop_id": "SHOP-001",
      "shop_name": "Ahmad's Supermarket",
      "collection_date": "2026-01-09",
      "items_count": 3,
      "expected_waste": [
        {
          "order_item_id": "ITEM-456",
          "product_name": "Baklava Box",
          "quantity_delivered": 10,
          "delivered_at": "2026-01-01",
          "expires_at": "2026-01-08"
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

### Step 2: Set Expected Waste Dates

**Endpoint:** `POST /api/external/v1/waste/expected`

Transportation MVP calls this endpoint daily (morning) to request shops that need waste collection.

**Request Body:**

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

**Response:**

```json
{
  "data": {
    "updated": 2
  },
  "message": "Expected waste dates updated for 2 shop(s)"
}
```

### Implementation in Melo ERP

In your `ShopsController` or service:

```php
// Daily cron job (4 AM)
public function calculateExpectedWaste()
{
    // Find items that expire today or have expired
    $expiredItems = ShopItem::where('expires_at', '<=', today())
        ->get()
        ->groupBy('shop_id');

    // Send to Transportation MVP
    $shops = $expiredItems->keys()
        ->map(fn($shopId) => [
            'external_shop_id' => $shopId,
            'expected_waste_date' => today()->toDateString(),
        ])
        ->toArray();

    Http::withToken($transportationMvpKey)
        ->post('https://transportation-app.alsabiqoon.com/api/external/v1/waste/expected', [
            'shops' => $shops,
        ]);
}
```

---

## Phase 4: Waste Collection Callback (Your Implementation)

When a driver logs waste at a shop, Transportation MVP sends a callback to Melo ERP.

### Callback Endpoint (Melo ERP)

You must implement this endpoint in Melo ERP:

```
POST /api/v1/waste/callback
```

**Headers:**

```
X-API-Key: [Your API Key]
X-Webhook-Signature: [HMAC-SHA256 signature]
Content-Type: application/json
```

### Callback Request Body

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
    },
    {
      "order_item_id": "ITEM-457",
      "product_name": "Kunafa Tray",
      "quantity_delivered": 5,
      "pieces_returned": 0,
      "pieces_sold": 5,
      "waste_date": "2026-01-02",
      "notes": null
    }
  ]
}
```

### Implementation Example (Laravel)

```php
// In WasteCallbackController.php
public function handle(Request $request)
{
    // Verify webhook signature
    if (!$this->verifySignature($request)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    $data = $request->json()->all();

    // Find or create waste collection record
    $wasteCollection = ShopWasteCollection::firstOrCreate(
        [
            'shop_id' => $data['shop_id'],
            'collection_date' => $data['collection_date'],
        ],
        [
            'collected_at' => $data['collected_at'],
            'collected_by_user_id' => $data['collected_by_user_id'],
        ]
    );

    // Process waste items
    foreach ($data['waste_items'] as $item) {
        WasteItem::create([
            'waste_collection_id' => $wasteCollection->id,
            'order_item_id' => $item['order_item_id'],
            'product_name' => $item['product_name'],
            'quantity_delivered' => $item['quantity_delivered'],
            'pieces_waste' => $item['pieces_returned'],
            'pieces_sold' => $item['pieces_sold'],
            'notes' => $item['notes'] ?? null,
        ]);
    }

    // Post journal entries for ledger
    // Example: Debit Inventory, Credit Sales (or similar)

    // Log for audit trail
    activity()
        ->causedBy(auth()->user())
        ->performedOn($wasteCollection)
        ->log('Waste collection received from Transportation MVP');

    return response()->json(['status' => 'success']);
}

private function verifySignature(Request $request): bool
{
    $signature = $request->header('X-Webhook-Signature');
    $body = $request->getContent();
    $secret = config('services.transportation-mvp.webhook_secret');

    $hash = hash_hmac('sha256', $body, $secret);
    return hash_equals($hash, $signature);
}
```

---

## Phase 5: Webhook Signature Verification

Transportation MVP signs all callbacks with HMAC-SHA256.

### Verify Signature (PHP)

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

### Verify Signature (Node.js)

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

---

## Error Handling & Retry Logic

### Callback Retry Strategy (Transportation MVP)

If your webhook returns non-2xx status:

| Attempt | Delay | Total |
|---------|-------|-------|
| 1 | Immediate | 0s |
| 2 | 10 seconds | 10s |
| 3 | 30 seconds | 40s |
| 4 | 1 minute | 100s |
| 5 | 2 minutes | 220s |

After 5 attempts, callback is abandoned and logged.

### Your Webhook Should

1. **Return 200+ status** immediately (don't process synchronously)
2. **Handle duplicate callbacks** (same collection_date + shop_id)
3. **Log all callbacks** for audit trail
4. **Send alerts** if critical errors occur

---

## Daily Operations

### Recommended Schedule

**2:00 AM** - Melo ERP calculates expected waste
```
GET /api/external/v1/waste/expected
POST /api/external/v1/waste/expected
```

**3:00 AM** - Transportation MVP auto-creates waste collection trips

**6:00 AM** - Drivers see waste collection tasks in app

**During Day** - Drivers log waste at shops

**Evening** - Callbacks sent to Melo ERP, ledger updated

---

## Testing Checklist

- [ ] Shop sync creates new shops
- [ ] Shop sync updates existing shops
- [ ] Shop sync handles invalid coordinates gracefully
- [ ] API key authentication works
- [ ] Waste collection route returns expected shops
- [ ] Expected waste dates are set correctly
- [ ] Callback is received with correct data
- [ ] Callback signature verifies successfully
- [ ] Callback retries on non-200 status
- [ ] Ledger entries are created after callback

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 401 Unauthorized | Check X-API-Key header |
| 422 Validation Error | Verify shop object structure |
| Callback not received | Check firewall/network, verify webhook_url in config |
| Callback signature invalid | Ensure webhook_secret is correct |
| Missing shops in app | Run shop sync again |
| Stale expected waste | Check if scheduled cron job is running |

---

## Support

For integration issues, contact:
- **Email**: integration@transportation-mvp.app
- **Slack**: #integrations
- **Docs**: https://docs.transportation-mvp.app
