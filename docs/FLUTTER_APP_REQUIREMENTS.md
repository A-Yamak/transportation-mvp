# Flutter Driver App - Requirements Specification

> **Document Version**: 1.1
> **Last Updated**: January 2026
> **Status**: Planning Phase
> **Primary Language**: Arabic (RTL) - English secondary

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
   - [1.4 Arabic-First Design Mandate](#14-arabic-first-design-mandate)
2. [Business Context](#2-business-context)
3. [User Personas](#3-user-personas)
4. [MVP Scope](#4-mvp-scope)
5. [Full-Featured Scope](#5-full-featured-scope)
   - [5.3.6 Localization (Arabic-First Design)](#536-localization-arabic-first-design)
6. [Technical Architecture](#6-technical-architecture)
7. [Screen Specifications](#7-screen-specifications)
8. [API Contract](#8-api-contract)
9. [Offline Strategy](#9-offline-strategy)
10. [Security Requirements](#10-security-requirements)
11. [Performance Requirements](#11-performance-requirements)
12. [Testing Strategy](#12-testing-strategy)
13. [Deployment Strategy](#13-deployment-strategy)
14. [Future Considerations](#14-future-considerations)

---

## 1. Executive Summary

### 1.1 Purpose

The Transportation Driver App is an **Arabic-first** mobile application that enables drivers to:
- View and manage assigned delivery trips
- Navigate to destinations efficiently
- Record delivery completions with proof
- Track kilometers driven for accurate billing

**Language Strategy**: This app is designed primarily for Arabic-speaking drivers in Jordan. The UI is **right-to-left (RTL) by default** with Arabic as the primary language. English is supported as a secondary language for accessibility.

### 1.2 Business Value

| Benefit | Impact |
|---------|--------|
| **Accurate KM Tracking** | GPS-based tracking ensures billing accuracy, eliminating disputes with clients |
| **Route Optimization** | Pre-optimized routes reduce fuel costs and delivery time |
| **Real-time Visibility** | Admin and clients can track delivery progress |
| **Proof of Delivery** | Photos/signatures protect against false claims |
| **Reduced Manual Work** | Automatic callbacks to client ERPs eliminate manual status updates |

### 1.3 MVP vs Full-Featured Summary

| Aspect | MVP | Full-Featured |
|--------|-----|---------------|
| **Timeline** | 4-6 weeks | 12-16 weeks |
| **Core Flow** | View trips → Navigate → Complete | Full workflow with all edge cases |
| **Offline** | Basic caching | Full offline-first architecture |
| **Proof of Delivery** | Optional notes | Photos, signatures, timestamps |
| **GPS Tracking** | Trip distance only | Real-time location streaming |
| **Languages** | Arabic (RTL) + English | Arabic + English + extensible |
| **Platforms** | Android only | iOS + Android |

### 1.4 Arabic-First Design Mandate

> **This is an Arabic-first application.** Arabic is the primary language, not a translation.

| Principle | Implementation |
|-----------|----------------|
| **Default Language** | Arabic (`ar`) - No language selection on first launch |
| **Default Direction** | RTL (right-to-left) - Layout built for Arabic, mirrored for English |
| **Target Users** | Arabic-speaking drivers in Jordan who may have limited English |
| **Font Selection** | Cairo or Noto Sans Arabic - optimized for Arabic readability |
| **Date/Number Format** | Arabic format with option for Eastern Arabic numerals (٠١٢٣) |
| **Address Display** | Arabic addresses displayed correctly with RTL alignment |
| **Error Messages** | Arabic error messages by default |

**Why This Matters:**
- 100% of our drivers are native Arabic speakers
- Many drivers have limited or no English proficiency
- Arabic-first shows respect for our users and market
- Better UX leads to higher adoption and fewer support calls
- Sets us apart from English-first apps that add Arabic as an afterthought

---

## 2. Business Context

### 2.1 The Problem We're Solving

**Current Pain Points:**
1. **Manual KM Recording**: Drivers estimate kilometers, leading to billing inaccuracies
2. **No Route Optimization**: Drivers choose their own routes, often inefficient
3. **Delayed Status Updates**: Client ERPs don't know delivery status until end of day
4. **No Proof of Delivery**: Disputes when customers claim non-delivery
5. **Paper-Based Logging**: Physical trip logs are error-prone and hard to audit

**Solution:**
A mobile app that automates the entire delivery workflow with GPS tracking, optimized routing, and digital proof of delivery.

### 2.2 How It Fits in the Ecosystem

```
┌─────────────────────────────────────────────────────────────────┐
│                    CLIENT ERP SYSTEMS                            │
│  (Sweets Factory, Other Businesses)                              │
└────────────────────────┬────────────────────────────────────────┘
                         │ POST /delivery-requests
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                 TRANSPORTATION BACKEND                           │
│                                                                  │
│  • Receives delivery requests from multiple ERPs                 │
│  • Optimizes routes via Google Maps API                          │
│  • Assigns trips to drivers                                      │
│  • Calculates costs (KM × price)                                 │
│  • Sends callbacks to ERPs on completion                         │
│  • Maintains double-entry ledger for accounting                  │
└────────────────────────┬────────────────────────────────────────┘
                         │ REST API
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                  FLUTTER DRIVER APP                              │
│                                                                  │
│  • Driver sees today's assigned trips                            │
│  • Taps to navigate (opens Google Maps)                          │
│  • Marks arrivals and completions                                │
│  • Captures proof (photos/signatures)                            │
│  • GPS tracks actual kilometers driven                           │
│  • Works offline with sync when online                           │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Cost-Saving Strategy: Navigation

**Critical Decision**: We do NOT build in-app turn-by-turn navigation.

**Why:**
- Google Maps Navigation SDK costs **$0.50 per session** (~$15/day for 30 deliveries)
- Opening the device's Google Maps app is **FREE**
- Drivers are already familiar with Google Maps
- Reduces app complexity and maintenance burden

**How it works:**
```
Driver taps "Navigate" → App opens Google Maps with destination
                       → Driver follows directions
                       → Returns to our app to mark complete
```

This single decision saves **~$450/month** in API costs for a single-vehicle operation.

---

## 3. User Personas

### 3.1 Primary User: The Driver

**Name**: أحمد (Ahmad) - represents our drivers

**Profile:**
- Age: 28-45
- Primary language: **Arabic** (العربية)
- Tech comfort: Basic smartphone user
- Uses: WhatsApp, Google Maps, basic apps
- Phone: Mid-range Android (Samsung A-series, Xiaomi)
- Pain points: Complex apps, small buttons, English-only interfaces

**Needs:**
- **Arabic interface by default** - no language selection required on first launch
- Large, clear buttons (RTL layout)
- Simple workflow (as few taps as possible)
- Works even with poor connectivity
- Quick access to navigation
- Arabic address display and input

**A Typical Day:**
1. 7:00 AM - Opens app, sees 12 deliveries assigned
2. 7:15 AM - Starts trip, app begins GPS tracking
3. 7:30 AM - Arrives at first stop, marks "Arrived"
4. 7:35 AM - Customer receives goods, driver marks "Complete"
5. ... repeats for each destination ...
6. 2:00 PM - All deliveries done, trip completes
7. 2:05 PM - App shows summary: 45.3 km driven, 12 deliveries

### 3.2 Secondary User: Operations Manager

**Interaction with app**: None directly (uses admin panel)

**Needs from the app:**
- Accurate location data for tracking
- Timestamps for performance monitoring
- Proof of delivery for dispute resolution

---

## 4. MVP Scope

### 4.1 MVP Philosophy

> "Ship the smallest thing that delivers business value"

**MVP Goal**: A driver can complete a full delivery day using the app, with accurate KM tracking.

**Included in MVP**: Arabic language (RTL), English language, basic GPS tracking.

**Not in MVP**: Photos, signatures, offline mode, iOS, fancy UI animations.

### 4.2 MVP Features

#### 4.2.1 Authentication

| Feature | Description | Business Reason |
|---------|-------------|-----------------|
| Login with email/password | Standard OAuth2 flow | Secure access |
| Persistent session | Stay logged in for 7 days | Reduce daily friction |
| Logout | Clear tokens and data | Security when sharing device |

**User Flow:**
```
App Launch → Check stored token
           → Valid? → Home Screen
           → Invalid? → Login Screen → Enter credentials → Home Screen
```

#### 4.2.2 Today's Trips List

| Feature | Description | Business Reason |
|---------|-------------|-----------------|
| View assigned trips | List of today's delivery requests | Know what to deliver |
| Trip summary | Destination count, total KM estimate | Plan the day |
| Status indicators | Not started / In Progress / Completed | Track progress |
| Pull-to-refresh | Update trip list | Get new assignments |

**Data Displayed (Arabic RTL - Default View):**
```
┌─────────────────────────────────────────┐
│    ↻                      رحلات اليوم   │
├─────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ │
│ │                          رحلة #1234 │ │
│ │             كم 32~ • 8 وجهات        │ │
│ │            [ابدأ الرحلة]            │ │
│ └─────────────────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │
│ │ مكتمل ✓                   رحلة #1235│ │
│ │        كم 18.5 فعلي • 5 وجهات       │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

**English LTR (Optional View):**
```
┌─────────────────────────────────────────┐
│ Today's Trips                      ↻    │
├─────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ │
│ │ Trip #1234                          │ │
│ │ 8 destinations • ~32 km             │ │
│ │ [Start Trip]                        │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

#### 4.2.3 Trip Details & Destinations

| Feature | Description | Business Reason |
|---------|-------------|-----------------|
| Optimized destination list | Ordered by route optimization | Efficient delivery |
| Destination details | Address, customer info, notes | Know where to go |
| Status per destination | Pending / Arrived / Completed / Failed | Track each stop |
| Navigate button | Opens Google Maps | Get directions |

**Destination Card (Arabic RTL):**
```
┌─────────────────────────────────────────┐
│                        مخبز الحلويات .1 │
│          شارع الملك عبدالله 123، عمان   │
│                       طلب #ORD-5678     │
│    ─────────────────────────────────    │
│   [تم الوصول ✓]  [📍 التوجيه]           │
└─────────────────────────────────────────┘
```

**Destination Card (English LTR):**
```
┌─────────────────────────────────────────┐
│ 1. Sweety Bakery                        │
│    123 King Abdullah St, Amman          │
│    Order #ORD-5678                       │
│    ─────────────────────────────────    │
│    [📍 Navigate]  [✓ Mark Arrived]      │
└─────────────────────────────────────────┘
```

#### 4.2.4 Trip Execution Flow

| Action | API Call | What Happens |
|--------|----------|--------------|
| Start Trip | `POST /trips/{id}/start` | GPS tracking begins, status → in_progress |
| Navigate | Opens Google Maps URL | No API call |
| Mark Arrived | `POST /trips/{id}/arrive/{dest}` | Timestamp recorded |
| Mark Complete | `POST /trips/{id}/complete/{dest}` | Callback sent to client ERP |
| Mark Failed | `POST /trips/{id}/fail/{dest}` | With reason (not home, refused, etc.) |
| Complete Trip | `POST /trips/{id}/complete` | GPS tracking ends, KM calculated |

**State Machine:**
```
Trip:        not_started → in_progress → completed
                                      → cancelled

Destination: pending → arrived → completed
                             → failed
```

#### 4.2.5 GPS Tracking (MVP)

| Feature | Description | Business Reason |
|---------|-------------|-----------------|
| Track trip distance | Calculate total KM from GPS points | Accurate billing |
| Background tracking | Continue when app minimized | Complete trip coverage |
| Send on trip complete | Upload total KM to backend | Record actual distance |

**MVP Implementation:**
```dart
// Simple distance accumulation
class TripTracker {
  double _totalMeters = 0;
  Position? _lastPosition;

  void onLocationUpdate(Position position) {
    if (_lastPosition != null) {
      _totalMeters += Geolocator.distanceBetween(
        _lastPosition!.latitude,
        _lastPosition!.longitude,
        position.latitude,
        position.longitude,
      );
    }
    _lastPosition = position;
  }

  double get totalKilometers => _totalMeters / 1000;
}
```

**Not in MVP:**
- Real-time location streaming to server
- Detailed route recording
- Speed tracking
- Geofencing

#### 4.2.6 MVP Screens Summary

| Screen | Purpose |
|--------|---------|
| Splash | App loading, token check |
| Login | Email/password authentication |
| Home (Trips List) | Today's assigned trips |
| Trip Details | Destinations list with actions |
| Trip Summary | After completion, show stats |

### 4.3 MVP Technical Requirements

| Requirement | Specification |
|-------------|---------------|
| Platform | Android 8.0+ (API 26+) |
| Min device | 2GB RAM, GPS capability |
| **Languages** | **Arabic (default, RTL) + English** |
| Default locale | `ar` (Arabic) |
| Text direction | RTL primary, LTR for English |
| Offline | None (requires internet) |
| State management | Riverpod (simple, testable) |
| HTTP client | Dio |
| GPS | geolocator package |
| Localization | flutter_localizations + intl |

### 4.4 MVP API Endpoints Required

```
Authentication:
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
GET    /api/v1/auth/user

Driver Operations:
GET    /api/v1/driver/trips/today
GET    /api/v1/driver/trips/{id}
POST   /api/v1/driver/trips/{id}/start
POST   /api/v1/driver/trips/{id}/arrive/{destination_id}
POST   /api/v1/driver/trips/{id}/complete/{destination_id}
POST   /api/v1/driver/trips/{id}/fail/{destination_id}
POST   /api/v1/driver/trips/{id}/complete
GET    /api/v1/driver/navigation/{destination_id}
```

### 4.5 MVP Success Criteria

- [ ] Driver can log in and see assigned trips
- [ ] Driver can start a trip and GPS tracking begins
- [ ] Driver can navigate to each destination via Google Maps
- [ ] Driver can mark destinations as arrived/completed/failed
- [ ] Driver can complete trip and see total KM driven
- [ ] Backend receives accurate KM data for billing
- [ ] Client ERP receives callbacks on delivery completion

---

## 5. Full-Featured Scope

### 5.1 Full-Featured Philosophy

> "Build for scale, reliability, and edge cases"

**Goal**: A production-ready app that handles all real-world scenarios, works offline, provides complete proof of delivery, and scales to multiple drivers.

### 5.2 Feature Categories

```
MVP Features (from Section 4)
    │
    ├── Enhanced Authentication
    │   ├── Biometric login (fingerprint/face)
    │   ├── Session timeout handling
    │   └── Multi-device management
    │
    ├── Offline-First Architecture
    │   ├── Local database (Drift/Isar)
    │   ├── Sync queue for pending actions
    │   ├── Conflict resolution
    │   └── Offline map tiles caching
    │
    ├── Proof of Delivery
    │   ├── Photo capture (with timestamp overlay)
    │   ├── Digital signature capture
    │   ├── Recipient name entry
    │   └── Delivery notes
    │
    ├── Real-Time Location
    │   ├── Live location streaming to server
    │   ├── Route recording (polyline)
    │   ├── Speed monitoring
    │   └── Geofencing (auto-arrive detection)
    │
    ├── Enhanced UX
    │   ├── Arabic language support (RTL)
    │   ├── Dark mode
    │   ├── Haptic feedback
    │   ├── Voice announcements
    │   └── Accessibility (screen readers)
    │
    ├── Communication
    │   ├── Call customer (tap to dial)
    │   ├── WhatsApp integration
    │   ├── In-app messaging with dispatch
    │   └── Push notifications
    │
    ├── Driver Tools
    │   ├── Earnings dashboard
    │   ├── Trip history
    │   ├── Performance stats
    │   └── Fuel log
    │
    └── iOS Support
        └── Full feature parity with Android
```

### 5.3 Enhanced Features Detail

#### 5.3.1 Offline-First Architecture

**Why This Matters:**
- Jordan has spotty mobile coverage in some areas
- Drivers shouldn't be blocked by network issues
- Data integrity must be maintained

**How It Works:**
```
┌─────────────────────────────────────────────────────────────┐
│                      FLUTTER APP                             │
│                                                              │
│  ┌──────────────┐     ┌──────────────┐     ┌─────────────┐ │
│  │   UI Layer   │────▶│  Repository  │────▶│   Remote    │ │
│  │              │     │   Pattern    │     │   (API)     │ │
│  └──────────────┘     └──────┬───────┘     └─────────────┘ │
│                              │                              │
│                              ▼                              │
│                       ┌──────────────┐                      │
│                       │    Local     │                      │
│                       │  Database    │                      │
│                       │   (Drift)    │                      │
│                       └──────────────┘                      │
│                              │                              │
│                              ▼                              │
│                       ┌──────────────┐                      │
│                       │  Sync Queue  │                      │
│                       │  (Pending    │                      │
│                       │   Actions)   │                      │
│                       └──────────────┘                      │
└─────────────────────────────────────────────────────────────┘

Sync Flow:
1. Driver marks destination complete (offline)
2. Action saved to local DB + sync queue
3. UI updates immediately (optimistic)
4. When online: sync queue processes
5. Server confirms → remove from queue
6. Conflict? → Show resolution UI
```

**Local Database Schema:**
```sql
-- Cached trips (synced from server)
trips (
  id TEXT PRIMARY KEY,
  data TEXT,  -- JSON blob
  synced_at DATETIME,
  version INTEGER
)

-- Pending actions (not yet synced)
sync_queue (
  id INTEGER PRIMARY KEY,
  action TEXT,  -- arrive, complete, fail
  payload TEXT, -- JSON
  created_at DATETIME,
  retry_count INTEGER,
  last_error TEXT
)

-- GPS points (for route recording)
location_points (
  id INTEGER PRIMARY KEY,
  trip_id TEXT,
  lat REAL,
  lng REAL,
  accuracy REAL,
  speed REAL,
  timestamp DATETIME,
  synced INTEGER DEFAULT 0
)
```

#### 5.3.2 Proof of Delivery System

**Why This Matters:**
- Customers sometimes claim non-delivery
- Photos prove delivery location and condition
- Signatures provide legal confirmation
- Protects both driver and company

**Photo Capture Requirements:**
```
┌─────────────────────────────────────────┐
│         DELIVERY PROOF PHOTO            │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │                                 │   │
│  │      [Camera Viewfinder]        │   │
│  │                                 │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Overlay (burned into image):           │
│  • Timestamp: 2026-01-04 14:32:15      │
│  • GPS: 31.9539° N, 35.9106° E         │
│  • Trip: #1234                          │
│  • Destination: Sweety Bakery          │
│                                         │
│  [📷 Capture]                           │
└─────────────────────────────────────────┘
```

**Signature Capture:**
```dart
// Using signature_pad package
SignaturePad(
  onSave: (signature) async {
    final bytes = await signature.toPngBytes();
    await deliveryService.saveSignature(
      tripId: trip.id,
      destinationId: destination.id,
      signatureBytes: bytes,
      recipientName: _nameController.text,
    );
  },
)
```

**Upload Strategy:**
- Compress images to max 500KB (quality 70%)
- Upload in background after marking complete
- Retry failed uploads up to 3 times
- Store locally until confirmed uploaded

#### 5.3.3 Real-Time Location Streaming

**Why This Matters:**
- Admin can see driver locations on map
- Clients can track their delivery in real-time
- Route recording for audit and optimization
- Geofencing enables auto-detection of arrivals

**Implementation:**
```dart
class LocationStreamService {
  final _locationSettings = LocationSettings(
    accuracy: LocationAccuracy.high,
    distanceFilter: 50, // meters between updates
  );

  Stream<Position> get positionStream =>
    Geolocator.getPositionStream(locationSettings: _locationSettings);

  void startStreaming(String tripId) {
    positionStream.listen((position) {
      // Send to server (batched every 30 seconds)
      _locationBuffer.add(LocationPoint(
        tripId: tripId,
        lat: position.latitude,
        lng: position.longitude,
        accuracy: position.accuracy,
        speed: position.speed,
        timestamp: DateTime.now(),
      ));

      if (_locationBuffer.length >= 10 || _timeSinceLastSync > 30) {
        _syncLocations();
      }

      // Check geofences
      _checkGeofences(position);
    });
  }

  void _checkGeofences(Position position) {
    for (final destination in _activeDestinations) {
      final distance = Geolocator.distanceBetween(
        position.latitude,
        position.longitude,
        destination.lat,
        destination.lng,
      );

      if (distance < 100) { // Within 100 meters
        _showArrivalPrompt(destination);
      }
    }
  }
}
```

**Battery Optimization:**
```
High Accuracy Mode (GPS):
- When trip is active
- Every 50 meters or 10 seconds
- Battery: ~5%/hour

Low Power Mode (Network):
- When no active trip
- Every 5 minutes
- Battery: ~0.5%/hour
```

#### 5.3.4 Communication Features

**Call Customer:**
```dart
// Simple tap-to-call
void callCustomer(String phone) {
  launchUrl(Uri.parse('tel:$phone'));
}
```

**WhatsApp Integration:**
```dart
void whatsappCustomer(String phone, String message) {
  final encodedMessage = Uri.encodeComponent(message);
  launchUrl(Uri.parse('https://wa.me/$phone?text=$encodedMessage'));
}

// Pre-filled message templates
final templates = {
  'arriving': 'مرحبا، سأصل خلال 10 دقائق',
  'waiting': 'أنا بالخارج، يرجى استلام الطلب',
  'delay': 'عذراً، سأتأخر قليلاً بسبب الازدحام',
};
```

**Push Notifications:**
```
Notification Types:
• New trip assigned
• Trip cancelled
• Urgent message from dispatch
• Reminder: incomplete trip

Implementation: Firebase Cloud Messaging (FCM)
```

#### 5.3.5 Driver Dashboard

**Earnings View:**
```
┌─────────────────────────────────────────┐
│ My Earnings                             │
├─────────────────────────────────────────┤
│                                         │
│ This Month          ▼ January 2026      │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│                                         │
│ Trips Completed          45             │
│ Total KM               1,234.5          │
│ Total Earnings         JOD 617.25       │
│                                         │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│ Daily Breakdown                         │
│                                         │
│ Jan 4   8 trips   98.3 km   JOD 49.15  │
│ Jan 3   9 trips  112.1 km   JOD 56.05  │
│ Jan 2   7 trips   87.9 km   JOD 43.95  │
│ ...                                     │
└─────────────────────────────────────────┘
```

**Trip History:**
```
Past Trips (searchable, filterable)
- Date range filter
- Status filter (completed/failed)
- Search by destination name
- Export to PDF (for records)
```

#### 5.3.6 Localization (Arabic-First Design)

**Why Arabic-First Matters:**
- **Primary market is Jordan** - 100% of drivers are Arabic speakers
- Arabic is RTL (right-to-left) language - must be default
- App must work for drivers with zero English proficiency
- Proper Arabic UX shows professionalism
- Future expansion to other Arab countries (KSA, UAE, Egypt)

**Arabic-First Principles:**
1. **Default to Arabic** - No language selection on first launch
2. **RTL is default** - Layout mirrors for English, not other way around
3. **Arabic numerals optional** - Eastern Arabic numerals (٠١٢) vs Western (012)
4. **Arabic-friendly fonts** - Use fonts with excellent Arabic support (Noto Sans Arabic, Cairo, Tajawal)

**Implementation:**
```dart
// flutter_localizations + intl packages
MaterialApp(
  // ARABIC FIRST - Default locale is Arabic
  locale: const Locale('ar'),

  localizationsDelegates: [
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    AppLocalizations.delegate,
  ],
  supportedLocales: const [
    Locale('ar'),  // Arabic FIRST
    Locale('en'),  // English secondary
  ],

  // Automatic direction based on locale
  builder: (context, child) {
    return Directionality(
      textDirection: Localizations.localeOf(context).languageCode == 'ar'
          ? TextDirection.rtl
          : TextDirection.ltr,
      child: child!,
    );
  },
)
```

**Strings Organization:**
```
lib/l10n/
├── app_ar.arb      # Arabic strings (PRIMARY)
├── app_en.arb      # English strings (secondary)
└── l10n.yaml       # Config
```

**Sample Arabic Strings (app_ar.arb):**
```json
{
  "@@locale": "ar",
  "appTitle": "تطبيق السائق",
  "todaysTrips": "رحلات اليوم",
  "startTrip": "ابدأ الرحلة",
  "continueTrip": "متابعة",
  "navigate": "التوجيه",
  "markArrived": "تم الوصول",
  "markComplete": "تم التسليم",
  "markFailed": "فشل التسليم",
  "destinations": "وجهات",
  "kilometers": "كم",
  "tripCompleted": "اكتملت الرحلة",
  "noTripsToday": "لا توجد رحلات اليوم",
  "settings": "الإعدادات",
  "language": "اللغة",
  "logout": "تسجيل الخروج",
  "failureReasons": {
    "notHome": "العميل غير موجود",
    "refused": "رفض الاستلام",
    "wrongAddress": "عنوان خاطئ",
    "other": "سبب آخر"
  }
}
```

**Font Configuration:**
```yaml
# pubspec.yaml
flutter:
  fonts:
    - family: Cairo  # Excellent Arabic support
      fonts:
        - asset: fonts/Cairo-Regular.ttf
        - asset: fonts/Cairo-Bold.ttf
          weight: 700
```

```dart
// theme.dart
ThemeData(
  fontFamily: 'Cairo',  // Arabic-optimized font
  textTheme: TextTheme(
    // Larger text for Arabic readability
    bodyLarge: TextStyle(fontSize: 18),
    bodyMedium: TextStyle(fontSize: 16),
  ),
)
```

### 5.4 Full-Featured Screens

| Screen | Purpose | MVP | Full |
|--------|---------|-----|------|
| Splash | Loading, token check | ✓ | ✓ |
| Login | Authentication | ✓ | ✓ + Biometric |
| Home (Trips) | Today's trips | ✓ | ✓ + Pull-to-refresh animation |
| Trip Details | Destinations list | ✓ | ✓ + Map view |
| Navigation | Opens Google Maps | ✓ | ✓ |
| Arrival Confirmation | Mark arrived | ✓ | ✓ + Auto-detect |
| Delivery Completion | Mark complete | ✓ | ✓ + Photo + Signature |
| Delivery Failed | Mark failed | ✓ | ✓ + Reasons + Photo |
| Trip Summary | Stats after completion | ✓ | ✓ + Share |
| Trip History | Past trips | ✗ | ✓ |
| Earnings | Financial dashboard | ✗ | ✓ |
| Profile | Driver info, settings | ✗ | ✓ |
| Settings | Language, theme, etc. | ✗ | ✓ |
| Offline Queue | Pending syncs | ✗ | ✓ |

### 5.5 Full-Featured API Endpoints

```
MVP Endpoints (from Section 4.4)
+
Additional Endpoints:

Location Streaming:
POST   /api/v1/driver/location/batch         Batch upload GPS points

Proof of Delivery:
POST   /api/v1/driver/trips/{id}/destinations/{dest_id}/photo
POST   /api/v1/driver/trips/{id}/destinations/{dest_id}/signature

History & Stats:
GET    /api/v1/driver/trips/history          Past trips (paginated)
GET    /api/v1/driver/stats/earnings         Earnings summary
GET    /api/v1/driver/stats/monthly          Monthly breakdown

Profile:
GET    /api/v1/driver/profile
PATCH  /api/v1/driver/profile
POST   /api/v1/driver/profile/photo

Push Notifications:
POST   /api/v1/driver/device                 Register FCM token
DELETE /api/v1/driver/device/{token}         Unregister token
```

---

## 6. Technical Architecture

### 6.1 Project Structure

```
flutter_app/
├── lib/
│   ├── main.dart
│   ├── app.dart                    # MaterialApp configuration
│   │
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart     # Dio setup, interceptors
│   │   │   ├── api_endpoints.dart  # URL constants
│   │   │   └── api_exceptions.dart # Custom exceptions
│   │   ├── auth/
│   │   │   ├── auth_service.dart   # Token management
│   │   │   └── auth_interceptor.dart
│   │   ├── database/
│   │   │   ├── app_database.dart   # Drift database
│   │   │   └── tables/             # Table definitions
│   │   ├── location/
│   │   │   ├── location_service.dart
│   │   │   └── trip_tracker.dart
│   │   └── sync/
│   │       ├── sync_service.dart
│   │       └── sync_queue.dart
│   │
│   ├── features/
│   │   ├── auth/
│   │   │   ├── data/
│   │   │   │   ├── auth_repository.dart
│   │   │   │   └── models/
│   │   │   ├── presentation/
│   │   │   │   ├── login_screen.dart
│   │   │   │   └── widgets/
│   │   │   └── providers/
│   │   │       └── auth_provider.dart
│   │   │
│   │   ├── trips/
│   │   │   ├── data/
│   │   │   │   ├── trips_repository.dart
│   │   │   │   └── models/
│   │   │   │       ├── trip.dart
│   │   │   │       └── destination.dart
│   │   │   ├── presentation/
│   │   │   │   ├── trips_list_screen.dart
│   │   │   │   ├── trip_details_screen.dart
│   │   │   │   ├── trip_summary_screen.dart
│   │   │   │   └── widgets/
│   │   │   │       ├── trip_card.dart
│   │   │   │       └── destination_card.dart
│   │   │   └── providers/
│   │   │       └── trips_provider.dart
│   │   │
│   │   ├── delivery/
│   │   │   ├── data/
│   │   │   │   └── delivery_repository.dart
│   │   │   ├── presentation/
│   │   │   │   ├── arrival_screen.dart
│   │   │   │   ├── completion_screen.dart
│   │   │   │   └── widgets/
│   │   │   │       ├── photo_capture.dart
│   │   │   │       └── signature_pad.dart
│   │   │   └── providers/
│   │   │
│   │   ├── navigation/
│   │   │   └── navigation_service.dart
│   │   │
│   │   ├── earnings/              # Full-featured only
│   │   └── settings/              # Full-featured only
│   │
│   ├── shared/
│   │   ├── widgets/
│   │   │   ├── app_button.dart
│   │   │   ├── loading_overlay.dart
│   │   │   └── error_view.dart
│   │   ├── theme/
│   │   │   ├── app_theme.dart
│   │   │   └── app_colors.dart
│   │   └── utils/
│   │       ├── date_utils.dart
│   │       └── format_utils.dart
│   │
│   ├── l10n/                      # Localization files (Arabic-first)
│   │   ├── app_ar.arb             # Arabic - PRIMARY template
│   │   └── app_en.arb             # English - secondary
│   │
│   └── router/
│       └── app_router.dart        # go_router configuration
│
├── test/
│   ├── unit/
│   ├── widget/
│   └── integration/
│
├── android/
├── ios/
├── pubspec.yaml
└── README.md
```

### 6.2 State Management

**Choice: Riverpod**

**Why Riverpod:**
- Compile-time safety (no runtime errors)
- Testable (easy to mock providers)
- Scoped state (auto-dispose when not needed)
- Works well with async data (AsyncValue)
- No BuildContext dependency (use anywhere)

**Provider Structure:**
```dart
// Auth state
@riverpod
class Auth extends _$Auth {
  @override
  Future<User?> build() async {
    return await ref.read(authRepositoryProvider).getCurrentUser();
  }

  Future<void> login(String email, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() =>
      ref.read(authRepositoryProvider).login(email, password)
    );
  }
}

// Trips state
@riverpod
Future<List<Trip>> todaysTrips(TodaysTripsRef ref) async {
  return await ref.read(tripsRepositoryProvider).getTodaysTrips();
}

// Active trip state
@riverpod
class ActiveTrip extends _$ActiveTrip {
  @override
  Trip? build() => null;

  void setActiveTrip(Trip trip) => state = trip;
  void clearActiveTrip() => state = null;
}
```

### 6.3 Dependencies

**MVP Dependencies:**
```yaml
dependencies:
  flutter:
    sdk: flutter

  # State Management
  flutter_riverpod: ^2.5.0
  riverpod_annotation: ^2.3.0

  # Networking
  dio: ^5.4.0

  # Local Storage
  shared_preferences: ^2.2.0
  flutter_secure_storage: ^9.0.0

  # Location
  geolocator: ^11.0.0

  # Navigation
  go_router: ^13.0.0
  url_launcher: ^6.2.0

  # UI
  flutter_svg: ^2.0.0

  # Localization (Arabic-First) - MVP REQUIRED
  flutter_localizations:
    sdk: flutter
  intl: ^0.19.0

dev_dependencies:
  flutter_test:
    sdk: flutter
  riverpod_generator: ^2.4.0
  build_runner: ^2.4.0
  mocktail: ^1.0.0
```

**l10n.yaml Configuration:**
```yaml
arb-dir: lib/l10n
template-arb-file: app_ar.arb  # Arabic is the template (primary)
output-localization-file: app_localizations.dart
```

**Full-Featured Additional Dependencies:**
```yaml
dependencies:
  # Offline Database
  drift: ^2.15.0
  sqlite3_flutter_libs: ^0.5.0

  # Image/Signature
  image_picker: ^1.0.0
  image: ^4.1.0
  signature: ^5.4.0

  # Push Notifications
  firebase_messaging: ^14.7.0

  # Permissions
  permission_handler: ^11.2.0

  # Connectivity
  connectivity_plus: ^5.0.0

  # Note: Localization packages already in MVP (Arabic-first requirement)
```

---

## 7. Screen Specifications

### 7.1 Login Screen

**Purpose**: Authenticate driver with email/password

**Wireframe (Arabic RTL - Default):**
```
┌─────────────────────────────────────────┐
│                                         │
│              [شعار التطبيق]              │
│                                         │
│            تطبيق السائق                  │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │                  البريد الإلكتروني│   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ 👁                    كلمة المرور│   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │          تسجيل الدخول            │   │
│  └─────────────────────────────────┘   │
│                                         │
│           نسيت كلمة المرور؟             │
│                                         │
│  ────────────────────────────────────  │
│          [🌐 English]                   │
│                                         │
└─────────────────────────────────────────┘
```

**Wireframe (English LTR):**
```
┌─────────────────────────────────────────┐
│                                         │
│              [App Logo]                 │
│                                         │
│           Driver App                    │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ Email                           │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ Password                    👁  │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │           LOGIN                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│          Forgot Password?               │
│                                         │
│  ────────────────────────────────────  │
│          [🌐 العربية]                   │
│                                         │
└─────────────────────────────────────────┘
```

**Behavior:**
- Email validation (format check)
- Password visibility toggle (eye icon on RTL side)
- Loading state on submit
- Error messages in current language
- Language switcher at bottom
- Remember email option

### 7.2 Home Screen (Trips List)

**Purpose**: Show today's assigned trips

**Wireframe (Arabic RTL - Default):**
```
┌─────────────────────────────────────────┐
│ ● أحمد              رحلات اليوم       ≡ │
├─────────────────────────────────────────┤
│                                         │
│               الأحد، ٤ يناير ٢٠٢٦       │
│         كم 85~ تقديري • رحلات 3         │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ لم تبدأ          رحلة #1234   🚚│   │
│  │                    مصنع الحلويات│   │
│  │           كم 32~ • 8 وجهات      │   │
│  │                                  │   │
│  │    [        ابدأ الرحلة        ] │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ جارية            رحلة #1235   🚚│   │
│  │                     مخبز دليش   │   │
│  │     كم 12.3 حتى الآن • 5/3 وجهات│   │
│  │                                  │   │
│  │    [         متابعة           ] │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ مكتملة ✓         رحلة #1233   🚚│   │
│  │                     شركة ABC    │   │
│  │      كم 18.5 فعلي • 5 وجهات     │   │
│  └─────────────────────────────────┘   │
│                                         │
└─────────────────────────────────────────┘
```

**Wireframe (English LTR):**
```
┌─────────────────────────────────────────┐
│ ≡  Today's Trips              Ahmad ●  │
├─────────────────────────────────────────┤
│                                         │
│  Sunday, January 4, 2026                │
│  3 trips • ~85 km estimated             │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ 🚚 Trip #1234          NOT STARTED│  │
│  │ Sweets Factory                   │   │
│  │ 8 stops • ~32 km                 │   │
│  │                                  │   │
│  │ [        START TRIP         ]    │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

### 7.3 Trip Details Screen

**Purpose**: Show destinations with actions

**Wireframe:**
```
┌─────────────────────────────────────────┐
│ ←  Trip #1234                    ⋮     │
├─────────────────────────────────────────┤
│                                         │
│  Sweets Factory Deliveries              │
│  8 destinations • ~32 km                │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  Progress: ████████░░░░ 5/8             │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ ✓ 1. Sweety Bakery    COMPLETED │   │
│  │    123 King Abdullah St         │   │
│  │    Completed at 9:15 AM         │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ ● 2. Coffee House     ARRIVED   │   │
│  │    456 Rainbow St               │   │
│  │    Arrived at 9:32 AM           │   │
│  │    ────────────────────────     │   │
│  │    [✓ COMPLETE]  [✗ FAILED]     │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ ○ 3. Mini Market      PENDING   │   │
│  │    789 Gardens St               │   │
│  │    ────────────────────────     │   │
│  │    [📍 NAVIGATE]  [● ARRIVED]   │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ ○ 4. Grocery Store    PENDING   │   │
│  │    ...                          │   │
│  └─────────────────────────────────┘   │
│                                         │
└─────────────────────────────────────────┘
```

### 7.4 Completion Screen (Full-Featured)

**Purpose**: Capture proof of delivery

**Wireframe:**
```
┌─────────────────────────────────────────┐
│ ←  Complete Delivery                    │
├─────────────────────────────────────────┤
│                                         │
│  Coffee House                           │
│  456 Rainbow St                         │
│                                         │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│                                         │
│  Delivery Photo (optional)              │
│  ┌─────────────────────────────────┐   │
│  │                                 │   │
│  │         [📷 Add Photo]          │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Recipient Signature (optional)         │
│  ┌─────────────────────────────────┐   │
│  │                                 │   │
│  │     [✍️ Capture Signature]       │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Recipient Name                         │
│  ┌─────────────────────────────────┐   │
│  │ Ahmad Mohammed                  │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Notes                                  │
│  ┌─────────────────────────────────┐   │
│  │ Left with security guard        │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │      ✓ CONFIRM DELIVERY         │   │
│  └─────────────────────────────────┘   │
│                                         │
└─────────────────────────────────────────┘
```

---

## 8. API Contract

### 8.1 Authentication

**POST /api/v1/auth/login**
```json
// Request
{
  "email": "driver@example.com",
  "password": "password123"
}

// Response 200
{
  "access_token": "eyJ...",
  "refresh_token": "def...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": "uuid",
    "name": "Ahmad",
    "email": "driver@example.com"
  }
}

// Response 422
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

### 8.2 Driver Trips

**GET /api/v1/driver/trips/today**
```json
// Response 200
{
  "data": [
    {
      "id": "uuid",
      "delivery_request": {
        "id": "uuid",
        "business_name": "Sweets Factory"
      },
      "status": "not_started",
      "estimated_km": 32.5,
      "actual_km": null,
      "destinations_count": 8,
      "completed_count": 0,
      "destinations": [
        {
          "id": "uuid",
          "sequence_order": 1,
          "external_id": "ORD-5678",
          "name": "Sweety Bakery",
          "address": "123 King Abdullah St, Amman",
          "lat": 31.9539,
          "lng": 35.9106,
          "status": "pending",
          "notes": "Ring bell twice"
        }
      ]
    }
  ]
}
```

**POST /api/v1/driver/trips/{id}/start**
```json
// Request (empty body)

// Response 200
{
  "data": {
    "id": "uuid",
    "status": "in_progress",
    "started_at": "2026-01-04T09:00:00Z"
  },
  "message": "Trip started. GPS tracking enabled."
}
```

**POST /api/v1/driver/trips/{id}/arrive/{destination_id}**
```json
// Request
{
  "lat": 31.9539,
  "lng": 35.9106
}

// Response 200
{
  "data": {
    "id": "uuid",
    "status": "arrived",
    "arrived_at": "2026-01-04T09:32:00Z"
  }
}
```

**POST /api/v1/driver/trips/{id}/complete/{destination_id}**
```json
// Request (MVP)
{
  "notes": "Left with security"
}

// Request (Full-featured)
{
  "recipient_name": "Ahmad Mohammed",
  "notes": "Left with security",
  "photo_base64": "data:image/jpeg;base64,...",  // optional
  "signature_base64": "data:image/png;base64,..." // optional
}

// Response 200
{
  "data": {
    "id": "uuid",
    "status": "completed",
    "completed_at": "2026-01-04T09:35:00Z"
  },
  "message": "Delivery completed. Customer notified."
}
```

**POST /api/v1/driver/trips/{id}/complete**
```json
// Request
{
  "actual_km": 34.7,
  "location_points": [  // Full-featured only
    {"lat": 31.95, "lng": 35.91, "timestamp": "..."},
    ...
  ]
}

// Response 200
{
  "data": {
    "id": "uuid",
    "status": "completed",
    "started_at": "2026-01-04T09:00:00Z",
    "completed_at": "2026-01-04T14:30:00Z",
    "estimated_km": 32.5,
    "actual_km": 34.7,
    "destinations_completed": 8,
    "destinations_failed": 0
  },
  "message": "Trip completed. Great work!"
}
```

---

## 9. Offline Strategy

### 9.1 Offline Capabilities Matrix

| Feature | MVP | Full |
|---------|-----|------|
| View cached trips | ✗ | ✓ |
| Start trip offline | ✗ | ✓ |
| Mark arrived offline | ✗ | ✓ |
| Mark complete offline | ✗ | ✓ |
| GPS tracking offline | ✓ | ✓ |
| Photo capture offline | N/A | ✓ |
| Signature offline | N/A | ✓ |
| Sync when online | N/A | ✓ |

### 9.2 Sync Queue Design

```dart
class SyncQueue {
  final Database _db;
  final ApiClient _api;

  // Add action to queue
  Future<void> enqueue(SyncAction action) async {
    await _db.syncQueue.insert(SyncQueueEntry(
      action: action.type,
      payload: jsonEncode(action.payload),
      createdAt: DateTime.now(),
      retryCount: 0,
    ));

    // Try to sync immediately if online
    if (await _isOnline()) {
      await processQueue();
    }
  }

  // Process pending items
  Future<void> processQueue() async {
    final pending = await _db.syncQueue.getPending();

    for (final entry in pending) {
      try {
        await _executeAction(entry);
        await _db.syncQueue.delete(entry.id);
      } catch (e) {
        await _db.syncQueue.incrementRetry(
          entry.id,
          error: e.toString(),
        );

        if (entry.retryCount >= 3) {
          // Move to failed queue for manual review
          await _db.syncQueue.markFailed(entry.id);
        }
      }
    }
  }

  Future<void> _executeAction(SyncQueueEntry entry) async {
    switch (entry.action) {
      case 'trip_start':
        await _api.post('/trips/${entry.tripId}/start');
      case 'destination_arrive':
        await _api.post('/trips/${entry.tripId}/arrive/${entry.destId}');
      case 'destination_complete':
        await _api.post(
          '/trips/${entry.tripId}/complete/${entry.destId}',
          data: entry.payload,
        );
      // ... etc
    }
  }
}
```

### 9.3 Conflict Resolution

**Scenario**: Driver marks complete offline, but admin already cancelled the trip.

**Resolution Strategy:**
1. Server returns 409 Conflict with reason
2. App shows explanation to driver
3. Driver acknowledges
4. Local state updated to match server

```dart
// Handle conflict
on DioException catch (e) {
  if (e.response?.statusCode == 409) {
    final conflict = Conflict.fromJson(e.response!.data);

    await showConflictDialog(
      title: 'Delivery Update Failed',
      message: conflict.message,
      serverState: conflict.currentState,
    );

    // Update local state to match server
    await _db.destinations.update(
      destId,
      status: conflict.currentState,
    );
  }
}
```

---

## 10. Security Requirements

### 10.1 Authentication Security

| Requirement | Implementation |
|-------------|----------------|
| Token storage | flutter_secure_storage (Keychain/Keystore) |
| Token refresh | Auto-refresh when 401 received |
| Session timeout | 7 days inactivity |
| Biometric | Unlock stored token with fingerprint/face |

### 10.2 Data Security

| Requirement | Implementation |
|-------------|----------------|
| API communication | HTTPS only (certificate pinning optional) |
| Local database | Encrypted with SQLCipher (full-featured) |
| Sensitive logs | Strip tokens/passwords from logs |
| Screenshot protection | Disable screenshots on sensitive screens |

### 10.3 Location Privacy

```dart
// Only track when trip is active
// Never track when not working
// Clear location data after sync
// Don't store more than 24 hours locally
```

---

## 11. Performance Requirements

### 11.1 App Performance

| Metric | Target |
|--------|--------|
| Cold start | < 3 seconds |
| Screen transition | < 300ms |
| API response handling | < 100ms |
| Memory usage | < 150MB |
| Battery drain (active trip) | < 5%/hour |

### 11.2 Network Performance

| Metric | Target |
|--------|--------|
| API timeout | 30 seconds |
| Retry attempts | 3 with exponential backoff |
| Image upload size | Max 500KB (compressed) |
| Location batch size | 10 points or 30 seconds |

---

## 12. Testing Strategy

### 12.1 Test Types

| Type | Coverage | Tools |
|------|----------|-------|
| Unit tests | Business logic, utils | flutter_test |
| Widget tests | UI components | flutter_test |
| Integration tests | Full flows | integration_test |
| E2E tests | Real device flows | Patrol |

### 12.2 Key Test Scenarios

**Authentication:**
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Token refresh flow
- [ ] Session expiry handling
- [ ] Biometric unlock (full)

**Trip Flow:**
- [ ] Load today's trips
- [ ] Start trip → GPS begins
- [ ] Navigate opens Google Maps
- [ ] Mark arrived
- [ ] Mark complete
- [ ] Mark failed with reason
- [ ] Complete trip → KM recorded

**Offline (Full-Featured):**
- [ ] Queue actions when offline
- [ ] Sync when back online
- [ ] Handle conflicts
- [ ] Local data persistence

---

## 13. Deployment Strategy

### 13.1 MVP Deployment

**Android Only:**
1. Internal testing via Firebase App Distribution
2. Closed beta via Google Play Console
3. Production release

**Build Variants:**
```
debug    → Local development
staging  → Test against staging API
release  → Production API
```

### 13.2 Full-Featured Deployment

**iOS + Android:**
1. TestFlight (iOS) + Firebase App Distribution (Android)
2. Closed beta on both stores
3. Production release

**CI/CD Pipeline:**
```
Push to main
    → Run tests
    → Build APK/IPA
    → Upload to distribution
    → Notify team
```

---

## 14. Future Considerations

### 14.1 Potential Features (Post-Full)

| Feature | Description | Complexity |
|---------|-------------|------------|
| Multi-vehicle | Support multiple drivers/vehicles | Medium |
| Route editing | Driver can reorder stops | Medium |
| Break tracking | Log break times | Low |
| Fuel logging | Record fuel purchases | Low |
| Maintenance alerts | Vehicle service reminders | Medium |
| Chat with dispatch | Real-time messaging | High |
| Voice commands | Hands-free operation | High |
| Wear OS app | Smartwatch companion | Medium |

### 14.2 Scalability Considerations

**Current Design Supports:**
- Single driver operation
- ~50 destinations per day
- Basic offline capability

**Future Scaling Needs:**
- Fleet management (10+ vehicles)
- Real-time dispatch optimization
- Driver-to-driver handoffs
- Warehouse pickup scheduling

### 14.3 Technical Debt to Avoid

1. **Don't hardcode API URLs** → Use environment configs
2. **Don't skip tests** → Maintain 70%+ coverage
3. **Don't ignore errors** → Proper error handling from day 1
4. **Don't neglect logging** → Add analytics events early
5. **Don't couple to backend** → Repository pattern for flexibility

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| **Trip** | A driver's execution of a delivery request |
| **Delivery Request** | A batch of destinations from a client |
| **Destination** | A single delivery stop |
| **POD** | Proof of Delivery (photo/signature) |
| **Geofencing** | Auto-detection when entering an area |
| **Optimized Route** | Google Maps ordered stops for efficiency |

---

## Appendix B: Decision Log

| Date | Decision | Rationale |
|------|----------|-----------|
| Jan 2026 | **Arabic-first, RTL default** | 100% of drivers are Arabic speakers in Jordan. App must work for zero-English users. |
| Jan 2026 | Arabic in MVP (not post-MVP) | Core business requirement, not a "nice to have" |
| Jan 2026 | Cairo font family | Excellent Arabic rendering, free, widely supported |
| Jan 2026 | Use Riverpod for state | Compile-safe, testable, modern |
| Jan 2026 | No in-app navigation | Cost savings ($450/month) |
| Jan 2026 | Android-first MVP | 90% of drivers use Android |
| Jan 2026 | Drift for offline DB | Type-safe, reactive, Flutter-native |

---

*Document maintained by the development team. Update as requirements evolve.*
