# Sentry Error Tracking Setup Guide

Transportation MVP Application - Error Tracking and Monitoring Configuration

## Overview

Sentry is integrated into both the Flutter driver app and Laravel backend to provide comprehensive error tracking, performance monitoring, and release management.

**Current Status:**
- ✅ Flutter Sentry SDK: 8.14.2 (installed)
- ✅ Laravel Sentry: Configured via `config/sentry.php`
- ⏳ Production DSN: **Needs to be added to .env files**

## Quick Setup

### Step 1: Create Sentry Project

1. Go to https://sentry.io
2. Sign in or create account
3. Create two projects:
   - **Project Name**: `transportation-mvp-flutter` → Select Flutter platform
   - **Project Name**: `transportation-mvp-backend` → Select PHP/Laravel platform
4. Copy the DSN for each project

### Step 2: Configure Flutter App

#### Environment Setup

Add Sentry DSN to your build command:

```bash
# Development
flutter run \
  --dart-define=SENTRY_ENABLED=true \
  --dart-define=SENTRY_DSN=https://YOUR_FLUTTER_DSN@sentry.io/PROJECT_ID

# Production
flutter build apk \
  --dart-define=SENTRY_ENABLED=true \
  --dart-define=SENTRY_DSN=https://YOUR_FLUTTER_DSN@sentry.io/PROJECT_ID
```

#### Or via Configuration File

Create `lib/core/api/api_config.dart` with:

```dart
class ApiConfig {
  // ... existing config ...

  static const String sentryDsn = String.fromEnvironment(
    'SENTRY_DSN',
    defaultValue: '', // Empty means disabled
  );

  static bool get sentryEnabled => sentryDsn.isNotEmpty;
}
```

### Step 3: Configure Laravel Backend

#### Install Sentry

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=https://YOUR_BACKEND_DSN@sentry.io/PROJECT_ID
```

#### Environment Variables

Add to `.env`:

```bash
# Sentry Configuration
SENTRY_LARAVEL_DSN=https://YOUR_BACKEND_DSN@sentry.io/PROJECT_ID
SENTRY_ENVIRONMENT=production
SENTRY_RELEASE=1.0.0
SENTRY_SAMPLE_RATE=1.0
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1
SENTRY_ENABLE_LOGS=true
SENTRY_LOG_LEVEL=debug
SENTRY_SEND_DEFAULT_PII=false
```

#### Breadcrumbs Configuration

```bash
# Enable/Disable specific breadcrumbs
SENTRY_BREADCRUMBS_LOGS_ENABLED=true
SENTRY_BREADCRUMBS_CACHE_ENABLED=true
SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED=true
SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED=false
SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED=true
SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED=true
```

#### Performance Tracing

```bash
# Enable specific trace types
SENTRY_TRACE_QUEUE_ENABLED=true
SENTRY_TRACE_QUEUE_JOBS_ENABLED=true
SENTRY_TRACE_SQL_QUERIES_ENABLED=true
SENTRY_TRACE_VIEWS_ENABLED=true
SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED=true
SENTRY_TRACE_CACHE_ENABLED=true
SENTRY_TRACE_REDIS_COMMANDS=false
SENTRY_TRACE_NOTIFICATIONS_ENABLED=true
SENTRY_TRACE_MISSING_ROUTES_ENABLED=false
SENTRY_TRACE_CONTINUE_AFTER_RESPONSE=true
```

## Error Tracking

### Flutter Error Handling

Automatic error capture is already configured in `lib/main.dart`:

```dart
await SentryFlutter.init(
  (options) {
    options.dsn = ApiConfig.sentryDsn;
    options.tracesSampleRate = 0.1;          // 10% of transactions
    options.profilesSampleRate = 0.1;        // 10% of users profiled
    options.environment = ApiConfig.isProduction ? 'production' : 'development';
    options.attachScreenshot = true;         // Screenshots with errors
    options.attachViewHierarchy = true;      // Widget tree context
    options.reportPackages = true;           // Package information
    options.reportSilentFlutterErrors = true; // Silent errors too
  },
  appRunner: () => _runApp(),
);
```

### Manual Error Reporting

In your Flutter code, you can manually report errors:

```dart
import 'package:sentry_flutter/sentry_flutter.dart';

try {
  // Some risky operation
} catch (exception, stackTrace) {
  // Report to Sentry
  await Sentry.captureException(
    exception,
    stackTrace: stackTrace,
  );
}
```

### Add Context

```dart
// Add user context
Sentry.setUser(SentryUser(
  id: userId,
  email: userEmail,
  username: driverName,
));

// Add custom tags
Sentry.setTag('trip_id', tripId);
Sentry.setTag('destination_id', destinationId);

// Add breadcrumb
Sentry.addBreadcrumb(SentryBreadcrumb(
  message: 'Payment collection started',
  level: SentryLevel.info,
  category: 'payment',
));
```

### Laravel Error Handling

Automatic error capture is built into Laravel's exception handling. Errors are automatically sent to Sentry via the configured DSN.

#### Custom Error Reporting

```php
use Sentry\Laravel\Facade as Sentry;

try {
    // Some database operation
} catch (\Exception $e) {
    Sentry::captureException($e);
}
```

#### Add Context

```php
use Sentry\Laravel\Facade as Sentry;

// Identify user
Sentry::setUser([
    'id' => auth()->id(),
    'email' => auth()->user()->email,
    'username' => auth()->user()->name,
]);

// Add tags
Sentry::addBreadcrumb([
    'message' => 'Payment collection request',
    'level' => 'info',
    'category' => 'payment',
    'data' => [
        'trip_id' => $tripId,
        'amount' => $amountCollected,
    ],
]);

// Set custom context
Sentry::setContext('trip', [
    'id' => $tripId,
    'status' => $trip->status,
    'destinations' => count($trip->destinations),
]);
```

## Performance Monitoring

### Transaction Tracking

Sentry automatically tracks HTTP requests and database queries.

**Flutter - Manual Transactions:**

```dart
import 'package:sentry_flutter/sentry_flutter.dart';

final transaction = Sentry.startTransaction(
  'payment_collection',
  'http',
);

try {
  // Perform payment collection
  final result = await collectPayment(tripId, destinationId);
  transaction.finish();
} catch (e) {
  transaction.throwable = e;
  transaction.finish(status: SpanStatus.internalError());
  rethrow;
}
```

**Laravel - Custom Spans:**

```php
use Sentry\Laravel\Facade as Sentry;

$transaction = Sentry::getClient()->startTransaction([
    'name' => 'process_payment',
    'op' => 'payment',
]);

try {
    $paymentService->processPayment($tripId, $destinationId);
    $transaction->finish();
} catch (\Exception $e) {
    $transaction->finish(\Sentry\Tracing\SpanStatus::internalError());
    throw $e;
}
```

## Release Tracking

### Flutter Releases

```bash
# Create release
sentry-cli releases -o your-org -p transportation-mvp-flutter create 1.0.0

# Upload symbols (for native crashes)
sentry-cli releases -o your-org -p transportation-mvp-flutter files upload-sourcemaps ./build/app/intermediates/stripped_native_debug_symbols

# Finalize
sentry-cli releases -o your-org -p transportation-mvp-flutter finalize
```

### Laravel Releases

```bash
# Set release version
export SENTRY_RELEASE=1.0.0

# Deploy
php artisan sentry:deploy --release=1.0.0
```

## Sample Rate Configuration

**Recommended Settings:**

```
Development:
  - Sample Rate: 1.0 (100% - capture all errors)
  - Traces Sample Rate: 1.0 (100% - trace all requests)
  - Profiles Sample Rate: 0.1 (10% - profile subset)

Staging:
  - Sample Rate: 1.0 (100%)
  - Traces Sample Rate: 0.5 (50% - reduce quota usage)
  - Profiles Sample Rate: 0.1 (10%)

Production:
  - Sample Rate: 1.0 (100% - capture all errors)
  - Traces Sample Rate: 0.1 (10% - reduce quota usage)
  - Profiles Sample Rate: 0.01 (1% - minimal profiling)
```

## Ignoring Specific Errors/Routes

### Flutter - Ignore Exceptions

```dart
await SentryFlutter.init(
  (options) {
    options.dsn = ApiConfig.sentryDsn;
    // Ignore specific exceptions
    options.excludePathsRegExp = [
      RegExp('flutter_test'), // Ignore test framework errors
    ];
  },
);
```

### Laravel - Ignore Routes

```php
// config/sentry.php
'ignore_transactions' => [
    '/up',                    // Health check
    '/api/health',           // Custom health
    '/public/*',             // Static assets
],
```

### Laravel - Ignore Exceptions

```php
// config/sentry.php
'ignore_exceptions' => [
    ValidationException::class,  // Don't report validation errors
    ModelNotFoundException::class, // 404s
],
```

## Testing Sentry Configuration

### Flutter Test

```dart
void testSentryConfiguration() async {
  // Verify DSN is configured
  expect(ApiConfig.sentryEnabled, true);
  expect(ApiConfig.sentryDsn.isNotEmpty, true);

  // Test sending event
  await Sentry.captureMessage('Test message from Flutter');
}
```

### Laravel Test

```php
public function testSentryConfiguration()
{
    // Verify DSN is configured
    $this->assertNotEmpty(config('sentry.dsn'));

    // Test sending event
    Sentry::captureMessage('Test message from Laravel');
}
```

### Manual Testing

```bash
# Flutter - Send test error
flutter run --dart-define=SENTRY_DSN=YOUR_DSN

# Then in app, trigger error
// Use debug console to send:
// Sentry.captureMessage('Test message');

# Laravel - Send test error
php artisan tinker
>>> Sentry::captureMessage('Test from Laravel');
```

## Issues & Debugging

### No Events Showing Up?

1. **Check DSN**: Ensure DSN is correctly set
2. **Check Sample Rate**: Verify `sample_rate` is > 0
3. **Check Network**: Ensure app has internet connectivity
4. **Check Debug Logs**: Enable debug logs in Sentry config
5. **Verify Auth**: Check project API keys in Sentry dashboard

### Too Many Events?

Reduce sample rates:

```dart
// Flutter
options.sampleRate = 0.5;           // 50% of errors
options.tracesSampleRate = 0.1;     // 10% of transactions

// Laravel
SENTRY_SAMPLE_RATE=0.5
SENTRY_TRACES_SAMPLE_RATE=0.1
```

### Performance Impact?

- Profile sampling is minimal (0.1% recommended for production)
- Breadcrumbs can be disabled if problematic
- Use `beforeSend` to filter events

```dart
// Flutter
await SentryFlutter.init(
  (options) {
    options.beforeSend = (event, hint) {
      // Filter or modify events before sending
      if (event.level == SentryLevel.debug) {
        return null; // Don't send debug events
      }
      return event;
    };
  },
);
```

## Troubleshooting

### Events Delayed?

- Check Sentry status page: https://status.sentry.io
- Check network connectivity
- Verify project quota not exceeded

### Missing Source Maps (Flutter)?

```bash
# Upload debug symbols
flutter build apk --split-debug-info=./symbols
sentry-cli upload-dif -o your-org -p transportation-mvp-flutter ./symbols
```

### SQL Queries Not Appearing (Laravel)?

```php
// Enable in config/sentry.php
'breadcrumbs' => [
    'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),
],

'tracing' => [
    'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', true),
],
```

## Monitoring Key Flows

### Payment Collection Flow

Add breadcrumbs at key steps:

```dart
// Flutter
Sentry.addBreadcrumb(SentryBreadcrumb(
  message: 'Opened payment collection dialog',
  category: 'payment',
  level: SentryLevel.info,
  data: {'tripId': tripId, 'destinationId': destinationId},
));

// Collect payment
await ref.read(paymentCollectionProvider.notifier).submitPayment(...);

Sentry.addBreadcrumb(SentryBreadcrumb(
  message: 'Payment submitted',
  category: 'payment',
  level: SentryLevel.info,
  data: {'amount': amountCollected, 'method': paymentMethod},
));
```

### Tupperware Pickup Flow

```php
// Laravel
Sentry::addBreadcrumb([
    'message' => 'Tupperware pickup initiated',
    'level' => 'info',
    'category' => 'tupperware',
    'data' => [
        'shop_id' => $shopId,
        'items_count' => count($items),
    ],
]);
```

## Dashboard Queries

### Find Payment Errors

```
environment:production AND tags.payment_method:* AND level:error
```

### Find Performance Issues

```
environment:production AND transaction:payment_collection AND measurements.duration:>5000
```

### Find User Errors

```
user.id:USER_ID AND environment:production
```

## Next Steps

1. **Create Sentry Organization** → https://sentry.io
2. **Create Flutter Project** → Copy DSN to `.env` or build config
3. **Create Laravel Project** → Add `SENTRY_LARAVEL_DSN` to `.env`
4. **Test Both Integrations** → Send test events
5. **Configure Alerts** → Set up notifications for critical errors
6. **Review Dashboard** → Monitor errors in production
7. **Tune Sample Rates** → Balance coverage vs. quota usage

## Support & Documentation

- **Sentry Flutter SDK**: https://docs.sentry.io/platforms/flutter/
- **Sentry Laravel SDK**: https://docs.sentry.io/platforms/php/guides/laravel/
- **Release Management**: https://docs.sentry.io/product/releases/
- **Performance Monitoring**: https://docs.sentry.io/product/performance/

---

**Last Updated**: January 10, 2026
**Status**: Ready for Production Setup
