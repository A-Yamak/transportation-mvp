# Local Integration Test: Melo ERP ↔ Transportation App

This guide helps you validate the complete integration flow locally before production.

---

## Quick Start (3 Terminals)

### Terminal 1: Start Backend
```bash
cd /path/to/transportation-mvp
docker compose up -d
```

### Terminal 2: Start Webhook Receiver (simulates Melo ERP)
```bash
# Outside Docker - on your host machine
cd backend
php artisan webhook:receive --port=9999
```

### Terminal 3: Run Integration Test
```bash
docker compose exec backend php artisan test:melo-integration
```

---

## What the Test Does

The integration test simulates the complete delivery flow:

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Melo ERP      │────▶│  Transportation  │────▶│   Driver App    │
│  (Simulated)    │     │      API         │     │  (Simulated)    │
└─────────────────┘     └──────────────────┘     └─────────────────┘
        │                        │                        │
        │  1. Submit orders      │                        │
        │───────────────────────▶│                        │
        │                        │  2. Assign trip        │
        │                        │───────────────────────▶│
        │                        │                        │
        │                        │  3. Driver starts trip │
        │                        │◀───────────────────────│
        │                        │                        │
        │                        │  4. Complete delivery  │
        │  5. Receive callback   │◀───────────────────────│
        │◀───────────────────────│                        │
        │                        │                        │
```

### Step-by-Step Flow:

1. **ERP Submits Order** → `POST /api/v1/delivery-requests`
   - Uses Melo's field names (order_id, delivery_address, etc.)
   - System transforms to internal format using schema

2. **Admin Assigns Trip** → Trip created and assigned to driver

3. **Driver Logs In** → `POST /api/v1/auth/login`

4. **Driver Starts Trip** → `POST /api/v1/driver/trips/{id}/start`

5. **Driver Completes Each Destination**:
   - `POST /api/v1/driver/trips/{id}/destinations/{id}/arrive`
   - `POST /api/v1/driver/trips/{id}/destinations/{id}/complete`
   - **This triggers callback to Melo ERP!**

---

## Expected Callback Output

When the webhook receiver catches a callback, you'll see:

```
============================================================
[2026-01-07 15:30:45] POST /api/delivery-callback
------------------------------------------------------------
HEADERS:
  Authorization: Bearer callback_xxxxx...
  Content-Type: application/json
------------------------------------------------------------
BODY:
{
    "order_id": "MELO-20260107-001",
    "delivery_status": "completed",
    "delivered_at": "2026-01-07T15:30:45+00:00",
    "received_by": "Test Recipient",
    "driver_notes": "Delivered successfully"
}
============================================================
```

---

## Troubleshooting

### "Melo Group business not found"
Run the seeder first:
```bash
docker compose exec backend php artisan db:seed --class=MvpSeeder
```

### Callbacks not reaching webhook receiver
The callback URL uses `host.docker.internal` to reach your host machine from Docker. If this doesn't work:

1. Check your Docker version supports `host.docker.internal`
2. Or use your machine's local IP:
   ```bash
   docker compose exec backend php artisan test:melo-integration --webhook-url=http://192.168.1.100:9999
   ```

### "Connection refused" errors
Make sure the webhook receiver is running BEFORE starting the test.

---

## Testing with Real Melo ERP

Once local testing passes, you can test with the real Melo ERP:

1. **Update callback URL** in business settings to your production URL
2. **Melo ERP sends** delivery requests to your API
3. **Your system sends** callbacks back to Melo's endpoint

### Melo ERP Configuration Needed:
```
API Endpoint: https://your-domain.com/api/v1/delivery-requests
Method: POST
Headers:
  X-API-Key: <provided_api_key>
  Content-Type: application/json

Callback URL: https://erp.melogroup.jo/api/delivery-callback
```

---

## Test Data Reference

After running `MvpSeeder`:

| Resource | Value |
|----------|-------|
| **Melo API Key** | Shown in seeder output |
| **Driver Email** | driver@alsabiqoon.com |
| **Driver Password** | driver123 |
| **Admin Email** | admin@alsabiqoon.com |
| **Admin Password** | admin123 |

---

## Cleanup Test Data

To remove test data after running:
```bash
docker compose exec backend php artisan test:melo-integration --cleanup
```

Or manually:
```bash
docker compose exec backend php artisan tinker
>>> Trip::latest()->first()->delete()
>>> DeliveryRequest::latest()->first()->delete()
```
