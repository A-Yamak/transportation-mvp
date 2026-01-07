# Production Configuration Checklist

All configurations are ready. You just need to provide the values below.

---

## 1. Sentry Error Tracking

### Backend (`backend/.env`)
```env
SENTRY_LARAVEL_DSN=https://xxx@xxx.ingest.sentry.io/xxx
```

### Flutter (build command)
```bash
flutter build apk --dart-define=SENTRY_DSN=https://xxx@xxx.ingest.sentry.io/xxx
```

**Get DSN from:** https://sentry.io → Project Settings → Client Keys (DSN)

---

## 2. Firebase Push Notifications

### Step 1: Create Firebase Project
1. Go to https://console.firebase.google.com
2. Create new project: `transportation-mvp`
3. Enable Cloud Messaging

### Step 2: Android Setup
1. Add Android app with package: `com.alsabiqoon.driver_app`
2. Download `google-services.json`
3. Place in: `flutter_app/android/app/google-services.json`
4. Uncomment in `flutter_app/android/settings.gradle.kts`:
   ```kotlin
   id("com.google.gms.google-services") version "4.4.2" apply false
   ```
5. Uncomment in `flutter_app/android/app/build.gradle.kts`:
   ```kotlin
   id("com.google.gms.google-services")
   ```

### Step 3: iOS Setup
1. Add iOS app with bundle ID: `com.alsabiqoon.driver`
2. Download `GoogleService-Info.plist`
3. Place in: `flutter_app/ios/Runner/GoogleService-Info.plist`

### Step 4: Update Firebase Options
Edit `flutter_app/lib/firebase_options.dart` with values from Firebase Console:

```dart
static const FirebaseOptions android = FirebaseOptions(
  apiKey: 'YOUR_ANDROID_API_KEY',
  appId: '1:123456789:android:abc123',
  messagingSenderId: '123456789',
  projectId: 'transportation-mvp',
  storageBucket: 'transportation-mvp.appspot.com',
);

static const FirebaseOptions ios = FirebaseOptions(
  apiKey: 'YOUR_IOS_API_KEY',
  appId: '1:123456789:ios:abc123',
  messagingSenderId: '123456789',
  projectId: 'transportation-mvp',
  storageBucket: 'transportation-mvp.appspot.com',
  iosBundleId: 'com.alsabiqoon.driver',
);
```

---

## 3. Cloudflare R2 Storage

### Get Credentials
1. Go to https://dash.cloudflare.com
2. Click R2 → Manage R2 API Tokens
3. Create token with "Object Read & Write" permission
4. Note your Account ID from the URL or dashboard

### Backend (`backend/.env`)
```env
FILESYSTEM_DISK=r2
CLOUDFLARE_R2_ACCESS_KEY_ID=your_access_key_id
CLOUDFLARE_R2_SECRET_ACCESS_KEY=your_secret_access_key
CLOUDFLARE_R2_BUCKET=transportationapp
CLOUDFLARE_R2_ENDPOINT=https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com
CLOUDFLARE_R2_URL=https://files.yoursite.com  # Optional: custom domain
```

### Create Bucket
```bash
# Via Cloudflare Dashboard or Wrangler CLI
wrangler r2 bucket create transportationapp
```

---

## 4. Google Maps API

### Get API Key
1. Go to https://console.cloud.google.com/google/maps-apis
2. Create or select project
3. Enable APIs:
   - Directions API
   - Distance Matrix API
4. Create credentials → API Key
5. Restrict key:
   - Application: IP addresses (add server IP)
   - APIs: Directions API, Distance Matrix API

### Backend (`backend/.env`)
```env
GOOGLE_MAPS_API_KEY=AIzaSy...your_api_key
```

---

## 5. Production Build Commands

### Flutter APK
```bash
cd flutter_app

# Debug build (no external services)
flutter build apk

# Production build (with all services)
flutter build apk \
  --dart-define=API_BASE_URL=https://api.yoursite.com \
  --dart-define=SENTRY_DSN=https://xxx@sentry.io/xxx \
  --dart-define=FLUTTER_ENV=production
```

### Backend Docker
```bash
# Update compose.yaml with production values or use .env
docker compose up -d
```

---

## Quick Test Checklist

- [ ] Backend login works: `POST /api/v1/auth/login`
- [ ] Sentry receives test error
- [ ] Firebase push notification received
- [ ] Photo upload to R2 works
- [ ] Route optimization returns results

---

## Files to Update

| Service | File | What to Add |
|---------|------|-------------|
| Sentry Backend | `backend/.env` | `SENTRY_LARAVEL_DSN=` |
| Sentry Flutter | Build command | `--dart-define=SENTRY_DSN=` |
| Firebase Android | `flutter_app/android/app/google-services.json` | Download from Firebase |
| Firebase iOS | `flutter_app/ios/Runner/GoogleService-Info.plist` | Download from Firebase |
| Firebase Options | `flutter_app/lib/firebase_options.dart` | Fill in values |
| Cloudflare R2 | `backend/.env` | R2 credentials |
| Google Maps | `backend/.env` | `GOOGLE_MAPS_API_KEY=` |
