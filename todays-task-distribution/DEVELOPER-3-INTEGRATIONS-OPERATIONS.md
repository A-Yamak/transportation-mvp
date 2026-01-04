# Developer 3: Integrations & Operations (Google Maps, PayloadSchema, Callbacks)

**Date**: 2026-01-04
**Phase**: 2.2, 2.3, 2.4, 2.5 - Integration Services Implementation
**Estimated Time**: 6-8 hours
**Priority**: CRITICAL (Blocks delivery request creation and ERP integration)

---

## 🎯 Mission

Build the integration layer for external services (Google Maps API) and business operation services (payload transformation, ERP callbacks). These services enable route optimization, distance calculation, dynamic API field mapping, and completion notifications to client ERPs.

---

## 📋 Task Overview

| Task | Files | Tests Required | Time Estimate |
|------|-------|----------------|---------------|
| 1. Create Google Maps config | 1 config file | - | 15 min |
| 2. Create RouteOptimizer service | 1 service | Feature + Unit | 90 min |
| 3. Create DistanceCalculator service | 1 service | Feature + Unit | 60 min |
| 4. Create SchemaTransformer service | 1 service | Feature + Unit | 90 min |
| 5. Create CallbackService | 1 service | Feature + Unit | 60 min |
| 6. Write all tests | 6 test files | 80%+ coverage | 120 min |
| 7. Update .env.example | 1 file | - | 10 min |
| **Total** | **11 files** | **80%+ coverage** | **6-8 hours** |

---

## 🗂️ Files You Will Create (Zero Conflicts)

### Configuration (1 file)
```
config/google-maps.php
```

### Services (4 files)
```
app/Services/GoogleMaps/RouteOptimizer.php
app/Services/GoogleMaps/DistanceCalculator.php
app/Services/PayloadSchema/SchemaTransformer.php
app/Services/Callback/CallbackService.php
```

### Tests (6 files)
```
tests/Feature/RouteOptimizationTest.php
tests/Feature/PayloadTransformationTest.php
tests/Feature/CallbackServiceTest.php
tests/Unit/Services/GoogleMaps/RouteOptimizerTest.php
tests/Unit/Services/GoogleMaps/DistanceCalculatorTest.php
tests/Unit/Services/PayloadSchema/SchemaTransformerTest.php
```

### Environment (1 file to edit)
```
.env.example (add Google Maps variables only - no conflicts)
```

---

## 📐 Technical Specifications

### Configuration File

#### config/google-maps.php

**Purpose**: Centralize Google Maps API configuration and caching strategy

**File**: `config/google-maps.php`

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Google Maps API Key
    |--------------------------------------------------------------------------
    |
    | Your Google Maps API key with the following APIs enabled:
    | - Directions API (for route optimization)
    | - Distance Matrix API (for distance calculation)
    |
    | Get your key at: https://console.cloud.google.com/apis/credentials
    |
    */
    'api_key' => env('GOOGLE_MAPS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    |
    | Google Maps API endpoints
    |
    */
    'directions_url' => 'https://maps.googleapis.com/maps/api/directions/json',
    'distance_matrix_url' => 'https://maps.googleapis.com/maps/api/distancematrix/json',

    /*
    |--------------------------------------------------------------------------
    | Caching Strategy
    |--------------------------------------------------------------------------
    |
    | Cache Google Maps API responses to reduce costs and improve performance.
    | Routes rarely change for the same set of destinations.
    |
    */
    'cache' => [
        'enabled' => env('GOOGLE_MAPS_CACHE_ENABLED', true),
        'ttl' => env('GOOGLE_MAPS_CACHE_TTL', 900), // 15 minutes in seconds
        'prefix' => 'google_maps:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for Google Maps API requests
    |
    */
    'defaults' => [
        'mode' => 'driving', // driving, walking, bicycling, transit
        'avoid' => '', // tolls, highways, ferries
        'units' => 'metric', // metric or imperial
        'language' => 'en', // Language for responses (en, ar, etc.)
        'region' => 'jo', // Region bias (jo = Jordan)
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Optimization
    |--------------------------------------------------------------------------
    |
    | Settings for route optimization via Directions API
    |
    */
    'optimization' => [
        'max_waypoints' => 25, // Google Maps limit (free tier)
        'optimize_waypoints' => true, // Enable waypoint optimization
    ],

    /*
    |--------------------------------------------------------------------------
    | Distance Matrix
    |--------------------------------------------------------------------------
    |
    | Settings for distance calculations via Distance Matrix API
    |
    */
    'distance_matrix' => [
        'max_origins' => 25, // Google Maps limit
        'max_destinations' => 25, // Google Maps limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent exceeding Google Maps API rate limits
    |
    */
    'rate_limit' => [
        'enabled' => true,
        'max_requests_per_second' => 50, // Adjust based on your plan
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | How to handle Google Maps API errors
    |
    */
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay_ms' => 1000, // 1 second delay between retries
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Log API usage for cost monitoring
    | Directions API: $5 per 1,000 requests
    | Distance Matrix: $5 per 1,000 elements
    |
    */
    'cost_tracking' => [
        'enabled' => env('GOOGLE_MAPS_COST_TRACKING', false),
        'log_channel' => 'daily', // Laravel log channel
    ],
];
```

---

### Service Specifications

#### 1. RouteOptimizer Service

**File**: `app/Services/GoogleMaps/RouteOptimizer.php`

**Purpose**: Optimize delivery routes using Google Maps Directions API

**Key Methods**:

```php
<?php

declare(strict_types=1);

namespace App\Services\GoogleMaps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Optimizes delivery routes using Google Maps Directions API.
 *
 * Cost Strategy:
 * - Called ONCE when delivery request is created
 * - Response is cached for 15 minutes
 * - Returns optimized waypoint order and total distance
 */
class RouteOptimizer
{
    protected string $apiKey;
    protected string $baseUrl;
    protected bool $cacheEnabled;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('google-maps.api_key');
        $this->baseUrl = config('google-maps.directions_url');
        $this->cacheEnabled = config('google-maps.cache.enabled', true);
        $this->cacheTtl = config('google-maps.cache.ttl', 900);

        if (empty($this->apiKey)) {
            throw new RuntimeException('Google Maps API key not configured');
        }
    }

    /**
     * Optimize route for multiple destinations.
     *
     * @param array $destinations Array of destinations with lat/lng
     *   [
     *     ['address' => '...', 'lat' => 31.9, 'lng' => 35.9],
     *     ['address' => '...', 'lat' => 31.8, 'lng' => 35.8],
     *   ]
     * @param array $startPoint ['lat' => ..., 'lng' => ...] Starting point (depot)
     * @param array $options Additional options (mode, avoid, etc.)
     * @return array [
     *   'optimized_order' => [2, 0, 1], // Optimized waypoint indices
     *   'total_distance_meters' => 25000,
     *   'total_duration_seconds' => 1800,
     *   'polyline' => 'encoded_polyline_string',
     *   'legs' => [...], // Detailed leg-by-leg info
     * ]
     * @throws RuntimeException if API call fails
     */
    public function optimize(
        array $destinations,
        array $startPoint,
        array $options = []
    ): array {
        $this->validateDestinations($destinations);
        $this->validateStartPoint($startPoint);

        $cacheKey = $this->getCacheKey($destinations, $startPoint);

        if ($this->cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $response = $this->callDirectionsAPI($destinations, $startPoint, $options);

        $result = $this->parseDirectionsResponse($response);

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $result, $this->cacheTtl);
        }

        if (config('google-maps.cost_tracking.enabled')) {
            $this->logApiUsage('directions', count($destinations));
        }

        return $result;
    }

    /**
     * Call Google Maps Directions API with retry logic.
     *
     * @param array $destinations
     * @param array $startPoint
     * @param array $options
     * @return array API response
     * @throws RuntimeException
     */
    protected function callDirectionsAPI(
        array $destinations,
        array $startPoint,
        array $options
    ): array {
        $waypoints = $this->buildWaypointsString($destinations);
        $origin = "{$startPoint['lat']},{$startPoint['lng']}";

        // For round trip, end at start point
        $destination = $origin;

        $params = array_merge([
            'origin' => $origin,
            'destination' => $destination,
            'waypoints' => "optimize:true|{$waypoints}",
            'key' => $this->apiKey,
            'mode' => config('google-maps.defaults.mode', 'driving'),
            'units' => config('google-maps.defaults.units', 'metric'),
            'language' => config('google-maps.defaults.language', 'en'),
        ], $options);

        $maxAttempts = config('google-maps.retry.max_attempts', 3);
        $delay = config('google-maps.retry.delay_ms', 1000);

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(30)->get($this->baseUrl, $params);

                if ($response->successful()) {
                    $data = $response->json();

                    if ($data['status'] !== 'OK') {
                        throw new RuntimeException(
                            "Google Maps API error: {$data['status']} - " .
                            ($data['error_message'] ?? 'Unknown error')
                        );
                    }

                    return $data;
                }
            } catch (\Exception $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    usleep($delay * 1000); // Convert ms to microseconds
                }
            }
        }

        throw new RuntimeException(
            "Failed to call Google Maps Directions API after {$maxAttempts} attempts",
            0,
            $lastException
        );
    }

    /**
     * Parse Directions API response.
     *
     * @param array $response
     * @return array Structured result
     */
    protected function parseDirectionsResponse(array $response): array
    {
        $route = $response['routes'][0] ?? null;

        if (! $route) {
            throw new RuntimeException('No route found in Google Maps response');
        }

        $optimizedOrder = $route['waypoint_order'] ?? [];
        $legs = $route['legs'] ?? [];
        $polyline = $route['overview_polyline']['points'] ?? '';

        $totalDistance = 0;
        $totalDuration = 0;

        foreach ($legs as $leg) {
            $totalDistance += $leg['distance']['value'] ?? 0;
            $totalDuration += $leg['duration']['value'] ?? 0;
        }

        return [
            'optimized_order' => $optimizedOrder,
            'total_distance_meters' => $totalDistance,
            'total_distance_km' => round($totalDistance / 1000, 2),
            'total_duration_seconds' => $totalDuration,
            'total_duration_minutes' => round($totalDuration / 60, 0),
            'polyline' => $polyline,
            'legs' => $this->parseLegs($legs),
        ];
    }

    /**
     * Parse leg details from response.
     *
     * @param array $legs
     * @return array
     */
    protected function parseLegs(array $legs): array
    {
        return array_map(function ($leg) {
            return [
                'start_address' => $leg['start_address'] ?? '',
                'end_address' => $leg['end_address'] ?? '',
                'distance_meters' => $leg['distance']['value'] ?? 0,
                'distance_text' => $leg['distance']['text'] ?? '',
                'duration_seconds' => $leg['duration']['value'] ?? 0,
                'duration_text' => $leg['duration']['text'] ?? '',
            ];
        }, $legs);
    }

    /**
     * Build waypoints string for API request.
     *
     * @param array $destinations
     * @return string "31.9,35.9|31.8,35.8|..."
     */
    protected function buildWaypointsString(array $destinations): string
    {
        return collect($destinations)
            ->map(fn($d) => "{$d['lat']},{$d['lng']}")
            ->implode('|');
    }

    /**
     * Generate cache key based on destinations and start point.
     *
     * @param array $destinations
     * @param array $startPoint
     * @return string
     */
    protected function getCacheKey(array $destinations, array $startPoint): string
    {
        $prefix = config('google-maps.cache.prefix', 'google_maps:');
        $hash = md5(json_encode([
            'destinations' => $destinations,
            'start' => $startPoint,
        ]));

        return "{$prefix}route:{$hash}";
    }

    /**
     * Validate destinations array.
     *
     * @param array $destinations
     * @throws RuntimeException
     */
    protected function validateDestinations(array $destinations): void
    {
        if (empty($destinations)) {
            throw new RuntimeException('At least one destination is required');
        }

        $maxWaypoints = config('google-maps.optimization.max_waypoints', 25);
        if (count($destinations) > $maxWaypoints) {
            throw new RuntimeException(
                "Too many destinations. Maximum {$maxWaypoints} allowed."
            );
        }

        foreach ($destinations as $index => $destination) {
            if (! isset($destination['lat']) || ! isset($destination['lng'])) {
                throw new RuntimeException(
                    "Destination at index {$index} missing lat or lng"
                );
            }
        }
    }

    /**
     * Validate start point.
     *
     * @param array $startPoint
     * @throws RuntimeException
     */
    protected function validateStartPoint(array $startPoint): void
    {
        if (! isset($startPoint['lat']) || ! isset($startPoint['lng'])) {
            throw new RuntimeException('Start point must have lat and lng');
        }
    }

    /**
     * Log API usage for cost tracking.
     *
     * @param string $apiType
     * @param int $count
     */
    protected function logApiUsage(string $apiType, int $count): void
    {
        Log::channel(config('google-maps.cost_tracking.log_channel'))
            ->info("Google Maps API Usage: {$apiType}", [
                'type' => $apiType,
                'count' => $count,
                'timestamp' => now()->toIso8601String(),
            ]);
    }
}
```

---

#### 2. DistanceCalculator Service

**File**: `app/Services/GoogleMaps/DistanceCalculator.php`

**Purpose**: Calculate distances using Google Maps Distance Matrix API

```php
<?php

declare(strict_types=1);

namespace App\Services\GoogleMaps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Calculate distances using Google Maps Distance Matrix API.
 *
 * Cost Strategy:
 * - Batch up to 25 origins × 25 destinations per request
 * - Cache responses for 15 minutes
 * - Use for cost estimation before route optimization
 */
class DistanceCalculator
{
    protected string $apiKey;
    protected string $baseUrl;
    protected bool $cacheEnabled;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('google-maps.api_key');
        $this->baseUrl = config('google-maps.distance_matrix_url');
        $this->cacheEnabled = config('google-maps.cache.enabled', true);
        $this->cacheTtl = config('google-maps.cache.ttl', 900);

        if (empty($this->apiKey)) {
            throw new RuntimeException('Google Maps API key not configured');
        }
    }

    /**
     * Calculate distances between origins and destinations.
     *
     * @param array $origins Array of ['lat' => ..., 'lng' => ...]
     * @param array $destinations Array of ['lat' => ..., 'lng' => ...]
     * @param array $options Additional options
     * @return array [
     *   'rows' => [
     *     ['elements' => [
     *       ['distance' => ['value' => 1000, 'text' => '1.0 km'], 'duration' => [...]]
     *     ]]
     *   ]
     * ]
     * @throws RuntimeException
     */
    public function calculate(
        array $origins,
        array $destinations,
        array $options = []
    ): array {
        $this->validateInputs($origins, $destinations);

        $cacheKey = $this->getCacheKey($origins, $destinations);

        if ($this->cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $response = $this->callDistanceMatrixAPI($origins, $destinations, $options);

        $result = $this->parseDistanceMatrixResponse($response);

        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $result, $this->cacheTtl);
        }

        if (config('google-maps.cost_tracking.enabled')) {
            $elements = count($origins) * count($destinations);
            $this->logApiUsage('distance_matrix', $elements);
        }

        return $result;
    }

    /**
     * Calculate total distance from origin to all destinations.
     *
     * @param array $origin ['lat' => ..., 'lng' => ...]
     * @param array $destinations
     * @return float Total distance in meters
     */
    public function calculateTotalDistance(array $origin, array $destinations): float
    {
        $result = $this->calculate([$origin], $destinations);

        $totalDistance = 0;
        foreach ($result['rows'][0]['elements'] as $element) {
            $totalDistance += $element['distance']['value'] ?? 0;
        }

        return $totalDistance;
    }

    /**
     * Call Google Maps Distance Matrix API.
     *
     * @param array $origins
     * @param array $destinations
     * @param array $options
     * @return array
     * @throws RuntimeException
     */
    protected function callDistanceMatrixAPI(
        array $origins,
        array $destinations,
        array $options
    ): array {
        $originsString = $this->buildCoordinatesString($origins);
        $destinationsString = $this->buildCoordinatesString($destinations);

        $params = array_merge([
            'origins' => $originsString,
            'destinations' => $destinationsString,
            'key' => $this->apiKey,
            'mode' => config('google-maps.defaults.mode', 'driving'),
            'units' => config('google-maps.defaults.units', 'metric'),
            'language' => config('google-maps.defaults.language', 'en'),
        ], $options);

        $response = Http::timeout(30)->get($this->baseUrl, $params);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to call Google Maps Distance Matrix API');
        }

        $data = $response->json();

        if ($data['status'] !== 'OK') {
            throw new RuntimeException(
                "Google Maps API error: {$data['status']} - " .
                ($data['error_message'] ?? 'Unknown error')
            );
        }

        return $data;
    }

    /**
     * Parse Distance Matrix API response.
     *
     * @param array $response
     * @return array
     */
    protected function parseDistanceMatrixResponse(array $response): array
    {
        return [
            'origin_addresses' => $response['origin_addresses'] ?? [],
            'destination_addresses' => $response['destination_addresses'] ?? [],
            'rows' => $response['rows'] ?? [],
        ];
    }

    /**
     * Build coordinates string for API.
     *
     * @param array $coordinates
     * @return string "31.9,35.9|31.8,35.8"
     */
    protected function buildCoordinatesString(array $coordinates): string
    {
        return collect($coordinates)
            ->map(fn($c) => "{$c['lat']},{$c['lng']}")
            ->implode('|');
    }

    /**
     * Generate cache key.
     *
     * @param array $origins
     * @param array $destinations
     * @return string
     */
    protected function getCacheKey(array $origins, array $destinations): string
    {
        $prefix = config('google-maps.cache.prefix', 'google_maps:');
        $hash = md5(json_encode([
            'origins' => $origins,
            'destinations' => $destinations,
        ]));

        return "{$prefix}distance:{$hash}";
    }

    /**
     * Validate inputs.
     *
     * @param array $origins
     * @param array $destinations
     * @throws RuntimeException
     */
    protected function validateInputs(array $origins, array $destinations): void
    {
        if (empty($origins) || empty($destinations)) {
            throw new RuntimeException('Origins and destinations cannot be empty');
        }

        $maxOrigins = config('google-maps.distance_matrix.max_origins', 25);
        $maxDestinations = config('google-maps.distance_matrix.max_destinations', 25);

        if (count($origins) > $maxOrigins) {
            throw new RuntimeException("Maximum {$maxOrigins} origins allowed");
        }

        if (count($destinations) > $maxDestinations) {
            throw new RuntimeException("Maximum {$maxDestinations} destinations allowed");
        }
    }

    /**
     * Log API usage.
     *
     * @param string $apiType
     * @param int $elements
     */
    protected function logApiUsage(string $apiType, int $elements): void
    {
        Log::channel(config('google-maps.cost_tracking.log_channel'))
            ->info("Google Maps API Usage: {$apiType}", [
                'type' => $apiType,
                'elements' => $elements,
                'timestamp' => now()->toIso8601String(),
            ]);
    }
}
```

---

#### 3. SchemaTransformer Service

**File**: `app/Services/PayloadSchema/SchemaTransformer.php`

**Purpose**: Transform API payloads based on business schema configuration

```php
<?php

declare(strict_types=1);

namespace App\Services\PayloadSchema;

use App\Models\BusinessPayloadSchema;
use App\Models\Destination;
use InvalidArgumentException;

/**
 * Transforms API payloads between internal format and business-specific schemas.
 *
 * Allows different ERPs to integrate with different field names:
 * - ERP A sends "order_id", we map to "external_id"
 * - ERP B sends "delivery_location", we map to "address"
 */
class SchemaTransformer
{
    /**
     * Transform incoming request data to internal format.
     *
     * @param array $data Incoming data from ERP
     * @param BusinessPayloadSchema $schema Business schema configuration
     * @return array Transformed data in internal format
     */
    public function transformIncoming(array $data, BusinessPayloadSchema $schema): array
    {
        $mapping = $schema->request_schema;

        if (empty($mapping)) {
            // No schema defined, return data as-is
            return $data;
        }

        $transformed = [];

        foreach ($mapping as $internalField => $externalField) {
            $value = $this->extractValue($data, $externalField);

            if ($value !== null) {
                $transformed[$internalField] = $value;
            }
        }

        return $transformed;
    }

    /**
     * Transform multiple destinations from incoming request.
     *
     * @param array $destinations Array of destination data from ERP
     * @param BusinessPayloadSchema $schema
     * @return array Array of transformed destinations
     */
    public function transformIncomingDestinations(
        array $destinations,
        BusinessPayloadSchema $schema
    ): array {
        return array_map(
            fn($dest) => $this->transformIncoming($dest, $schema),
            $destinations
        );
    }

    /**
     * Transform outgoing data for callback to ERP.
     *
     * @param Destination $destination Completed destination
     * @param BusinessPayloadSchema $schema Business schema configuration
     * @return array Transformed data in ERP format
     */
    public function transformCallback(
        Destination $destination,
        BusinessPayloadSchema $schema
    ): array {
        $mapping = $schema->callback_schema;

        if (empty($mapping)) {
            // No schema defined, return default format
            return $this->getDefaultCallbackFormat($destination);
        }

        $transformed = [];

        $internalData = [
            'external_id' => $destination->external_id,
            'status' => $destination->status->value,
            'completed_at' => $destination->completed_at?->toIso8601String(),
            'arrived_at' => $destination->arrived_at?->toIso8601String(),
            'failure_reason' => $destination->failure_reason?->value,
            'failure_notes' => $destination->failure_notes,
            'signature_url' => $destination->signature_url,
            'photo_url' => $destination->photo_url,
            'address' => $destination->address,
            'coordinates' => [
                'lat' => $destination->lat,
                'lng' => $destination->lng,
            ],
        ];

        foreach ($mapping as $externalField => $internalField) {
            $value = $this->extractValue($internalData, $internalField);

            if ($value !== null) {
                $this->setValue($transformed, $externalField, $value);
            }
        }

        return $transformed;
    }

    /**
     * Extract value from nested array using dot notation.
     *
     * Examples:
     * - "order_id" → $data['order_id']
     * - "coordinates.latitude" → $data['coordinates']['latitude']
     *
     * @param array $data
     * @param string $path Dot-notation path
     * @return mixed|null
     */
    protected function extractValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Set value in nested array using dot notation.
     *
     * @param array &$array Array to modify (by reference)
     * @param string $path Dot-notation path
     * @param mixed $value Value to set
     */
    protected function setValue(array &$array, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (! isset($current[$key]) || ! is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Get default callback format (when no schema is defined).
     *
     * @param Destination $destination
     * @return array
     */
    protected function getDefaultCallbackFormat(Destination $destination): array
    {
        return [
            'external_id' => $destination->external_id,
            'status' => $destination->status->value,
            'completed_at' => $destination->completed_at?->toIso8601String(),
            'arrived_at' => $destination->arrived_at?->toIso8601String(),
            'failure_reason' => $destination->failure_reason?->value,
            'failure_notes' => $destination->failure_notes,
            'signature_url' => $destination->signature_url,
            'photo_url' => $destination->photo_url,
        ];
    }

    /**
     * Validate that required fields are present in incoming data.
     *
     * @param array $data Incoming data
     * @param array $requiredFields Array of required field names
     * @throws InvalidArgumentException if required fields are missing
     */
    public function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new InvalidArgumentException(
                'Missing required fields: ' . implode(', ', $missing)
            );
        }
    }
}
```

---

#### 4. CallbackService

**File**: `app/Services/Callback/CallbackService.php`

**Purpose**: Send completion callbacks to client ERPs

```php
<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Models\Destination;
use App\Services\PayloadSchema\SchemaTransformer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends delivery completion callbacks to client ERP systems.
 *
 * When a driver marks a destination as completed (or failed), this service
 * sends a HTTP callback to the client's configured webhook URL.
 */
class CallbackService
{
    protected SchemaTransformer $schemaTransformer;

    public function __construct(SchemaTransformer $schemaTransformer)
    {
        $this->schemaTransformer = $schemaTransformer;
    }

    /**
     * Send completion callback for a destination.
     *
     * @param Destination $destination Completed/failed destination
     * @return bool True if callback was sent successfully
     */
    public function sendCompletionCallback(Destination $destination): bool
    {
        $business = $destination->deliveryRequest->business;

        if (! $business->callback_url) {
            Log::warning('No callback URL configured for business', [
                'business_id' => $business->id,
                'destination_id' => $destination->id,
            ]);
            return false;
        }

        $schema = $business->payloadSchema;

        if (! $schema) {
            Log::warning('No payload schema configured for business', [
                'business_id' => $business->id,
                'destination_id' => $destination->id,
            ]);
            return false;
        }

        $payload = $this->schemaTransformer->transformCallback($destination, $schema);

        return $this->sendCallback($business->callback_url, $payload, $business->callback_api_key);
    }

    /**
     * Send HTTP callback to ERP.
     *
     * @param string $url Callback URL
     * @param array $payload Data to send
     * @param string|null $apiKey Optional API key for authentication
     * @return bool True if successful
     */
    protected function sendCallback(string $url, array $payload, ?string $apiKey = null): bool
    {
        try {
            $request = Http::timeout(30);

            if ($apiKey) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url, $payload);

            $success = $response->successful();

            Log::info('Callback sent to ERP', [
                'url' => $url,
                'status' => $response->status(),
                'success' => $success,
                'payload' => $payload,
            ]);

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send callback to ERP', [
                'url' => $url,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send test callback to verify ERP integration.
     *
     * @param string $url Callback URL
     * @param string|null $apiKey Optional API key
     * @return array ['success' => bool, 'status' => int, 'message' => string]
     */
    public function sendTestCallback(string $url, ?string $apiKey = null): array
    {
        $testPayload = [
            'external_id' => 'TEST-123',
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            'message' => 'This is a test callback from Transportation MVP',
        ];

        try {
            $request = Http::timeout(10);

            if ($apiKey) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url, $testPayload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Test callback sent successfully'
                    : 'Callback failed with status ' . $response->status(),
                'response_body' => $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 0,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }
}
```

---

## 🧪 Testing Requirements

### Feature Tests

Due to character limits, I'll provide key test cases. You should implement:

**tests/Feature/RouteOptimizationTest.php**:
- test_optimizes_route_for_multiple_destinations
- test_caches_optimization_results
- test_throws_exception_when_api_key_missing
- test_handles_google_maps_api_errors
- test_validates_destination_count_limit

**tests/Feature/PayloadTransformationTest.php**:
- test_transforms_incoming_data_with_schema
- test_transforms_callback_data_with_schema
- test_handles_nested_field_mapping
- test_returns_default_format_when_no_schema

**tests/Feature/CallbackServiceTest.php**:
- test_sends_callback_to_erp
- test_skips_callback_when_no_url_configured
- test_sends_test_callback_successfully

### Unit Tests

**tests/Unit/Services/GoogleMaps/RouteOptimizerTest.php**:
- test_builds_waypoints_string_correctly
- test_parses_directions_response
- test_validates_destinations
- test_generates_cache_key

**tests/Unit/Services/GoogleMaps/DistanceCalculatorTest.php**:
- test_builds_coordinates_string
- test_validates_input_limits

**tests/Unit/Services/PayloadSchema/SchemaTransformerTest.php**:
- test_extracts_value_with_dot_notation
- test_sets_value_with_dot_notation
- test_validates_required_fields

---

## 📝 Update .env.example

Add:

```env
# Google Maps API Configuration
GOOGLE_MAPS_API_KEY=
GOOGLE_MAPS_CACHE_ENABLED=true
GOOGLE_MAPS_CACHE_TTL=900
GOOGLE_MAPS_COST_TRACKING=false
```

---

## ✅ Completion Checklist

### Phase 1: Configuration (15 min)
- [ ] Create `config/google-maps.php`
- [ ] Update `.env.example`

### Phase 2: Services (4 hours)
- [ ] Create `RouteOptimizer` service
- [ ] Create `DistanceCalculator` service
- [ ] Create `SchemaTransformer` service
- [ ] Create `CallbackService`

### Phase 3: Testing (3 hours)
- [ ] Write all feature tests
- [ ] Write all unit tests
- [ ] All tests pass

### Phase 4: Quality (30 min)
- [ ] PSR-12 compliance
- [ ] PHPDoc coverage
- [ ] Code coverage >80%

---

## 🎯 Success Criteria

- ✅ 4 services implemented
- ✅ HTTP mocking in tests (no real API calls)
- ✅ All tests passing
- ✅ Code coverage >80%
- ✅ PSR-12 compliant

---

**Good luck! You're building the integration backbone of the system.**
