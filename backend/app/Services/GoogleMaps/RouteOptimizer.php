<?php

declare(strict_types=1);

namespace App\Services\GoogleMaps;

use App\Exceptions\GoogleMapsApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    protected ?string $apiKey;
    protected string $baseUrl;
    protected bool $cacheEnabled;
    protected int $cacheTtl;
    protected bool $mockMode;

    public function __construct()
    {
        $this->mockMode = config('google-maps.mock_mode', false);
        $this->apiKey = config('google-maps.api_key');

        // Allow empty API key in mock mode
        if (empty($this->apiKey) && ! $this->mockMode) {
            throw GoogleMapsApiException::invalidApiKey();
        }

        $this->baseUrl = config('google-maps.directions_url');
        $this->cacheEnabled = config('google-maps.cache.enabled', true);
        $this->cacheTtl = (int) config('google-maps.cache.ttl', 900);
    }

    /**
     * Optimize route for multiple destinations.
     *
     * @param  array  $destinations  Array of destinations with lat/lng
     *   [
     *     ['address' => '...', 'lat' => 31.9, 'lng' => 35.9],
     *     ['address' => '...', 'lat' => 31.8, 'lng' => 35.8],
     *   ]
     * @param  array  $startPoint  ['lat' => ..., 'lng' => ...] Starting point (depot)
     * @param  array  $options  Additional options (mode, avoid, etc.)
     * @return array [
     *   'optimized_order' => [2, 0, 1], // Optimized waypoint indices
     *   'total_distance_meters' => 25000,
     *   'total_duration_seconds' => 1800,
     *   'polyline' => 'encoded_polyline_string',
     *   'legs' => [...], // Detailed leg-by-leg info
     * ]
     *
     * @throws GoogleMapsApiException if API call fails
     */
    public function optimize(
        array $destinations,
        array $startPoint,
        array $options = []
    ): array {
        $this->validateDestinations($destinations);
        $this->validateStartPoint($startPoint);

        // Return mock data for testing without Google Maps API key
        if ($this->mockMode) {
            return $this->getMockResponse($destinations);
        }

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
     * @param  array  $destinations
     * @param  array  $startPoint
     * @param  array  $options
     * @return array API response
     *
     * @throws GoogleMapsApiException
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
                $timeout = config('google-maps.timeout', 30);
                $response = Http::timeout($timeout)->get($this->baseUrl, $params);

                if (! $response->successful()) {
                    throw GoogleMapsApiException::requestFailed(
                        'HTTP request failed',
                        $response->status()
                    );
                }

                $data = $response->json();

                if ($data['status'] !== 'OK') {
                    throw GoogleMapsApiException::requestFailed(
                        "{$data['status']} - ".($data['error_message'] ?? 'Unknown error')
                    );
                }

                return $data;
            } catch (\Exception $e) {
                $lastException = $e;
                if ($attempt < $maxAttempts) {
                    usleep($delay * 1000); // Convert ms to microseconds
                }
            }
        }

        throw GoogleMapsApiException::requestFailed(
            "Failed after {$maxAttempts} attempts: ".($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Parse Directions API response.
     *
     * @param  array  $response
     * @return array Structured result
     */
    protected function parseDirectionsResponse(array $response): array
    {
        $route = $response['routes'][0] ?? null;

        if (! $route) {
            throw GoogleMapsApiException::invalidResponse('No route found in response');
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
     * @param  array  $legs
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
     * @param  array  $destinations
     * @return string "31.9,35.9|31.8,35.8|..."
     */
    protected function buildWaypointsString(array $destinations): string
    {
        return collect($destinations)
            ->map(fn ($d) => "{$d['lat']},{$d['lng']}")
            ->implode('|');
    }

    /**
     * Generate cache key based on destinations and start point.
     *
     * @param  array  $destinations
     * @param  array  $startPoint
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
     * @param  array  $destinations
     *
     * @throws GoogleMapsApiException
     */
    protected function validateDestinations(array $destinations): void
    {
        if (empty($destinations)) {
            throw GoogleMapsApiException::invalidCoordinates('At least one destination is required');
        }

        $maxWaypoints = config('google-maps.optimization.max_waypoints', 25);
        if (count($destinations) > $maxWaypoints) {
            throw GoogleMapsApiException::tooManyWaypoints(count($destinations), $maxWaypoints);
        }

        foreach ($destinations as $index => $destination) {
            if (! isset($destination['lat']) || ! isset($destination['lng'])) {
                throw GoogleMapsApiException::invalidCoordinates(
                    "Destination at index {$index} missing lat or lng"
                );
            }
        }
    }

    /**
     * Validate start point.
     *
     * @param  array  $startPoint
     *
     * @throws GoogleMapsApiException
     */
    protected function validateStartPoint(array $startPoint): void
    {
        if (! isset($startPoint['lat']) || ! isset($startPoint['lng'])) {
            throw GoogleMapsApiException::invalidCoordinates('Start point must have lat and lng');
        }
    }

    /**
     * Log API usage for cost tracking.
     *
     * @param  string  $apiType
     * @param  int  $count
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

    /**
     * Generate mock response for testing without Google Maps API.
     *
     * @param  array  $destinations
     * @return array Mock optimization result
     */
    protected function getMockResponse(array $destinations): array
    {
        $count = count($destinations);

        // Generate sequential order (no real optimization)
        $order = range(0, $count - 1);

        // Estimate ~5km and 10 minutes per destination
        $distancePerDest = 5000; // 5km in meters
        $durationPerDest = 600;  // 10 minutes in seconds

        $legs = [];
        foreach ($destinations as $index => $dest) {
            $legs[] = [
                'start_address' => $index === 0 ? 'Depot' : ($destinations[$index - 1]['address'] ?? 'Previous'),
                'end_address' => $dest['address'] ?? "Destination {$index}",
                'distance_meters' => $distancePerDest,
                'distance_text' => '5.0 km',
                'duration_seconds' => $durationPerDest,
                'duration_text' => '10 mins',
            ];
        }

        $totalDistance = $distancePerDest * $count;
        $totalDuration = $durationPerDest * $count;

        Log::info('RouteOptimizer: Using MOCK MODE (no Google Maps API key)', [
            'destinations' => $count,
            'mock_distance_km' => $totalDistance / 1000,
        ]);

        return [
            'optimized_order' => $order,
            'total_distance_meters' => $totalDistance,
            'total_distance_km' => round($totalDistance / 1000, 2),
            'total_duration_seconds' => $totalDuration,
            'total_duration_minutes' => round($totalDuration / 60, 0),
            'polyline' => 'MOCK_POLYLINE_' . md5(json_encode($destinations)),
            'legs' => $legs,
        ];
    }
}
