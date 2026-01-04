<?php

declare(strict_types=1);

namespace App\Services\GoogleMaps;

use App\Exceptions\GoogleMapsApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    protected ?string $apiKey;
    protected string $baseUrl;
    protected bool $cacheEnabled;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = config('google-maps.api_key');

        if (empty($this->apiKey)) {
            throw GoogleMapsApiException::invalidApiKey();
        }

        $this->baseUrl = config('google-maps.distance_matrix_url');
        $this->cacheEnabled = config('google-maps.cache.enabled', true);
        $this->cacheTtl = (int) config('google-maps.cache.ttl', 900);
    }

    /**
     * Calculate distances between origins and destinations.
     *
     * @param  array  $origins  Array of ['lat' => ..., 'lng' => ...]
     * @param  array  $destinations  Array of ['lat' => ..., 'lng' => ...]
     * @param  array  $options  Additional options
     * @return array [
     *   'rows' => [
     *     ['elements' => [
     *       ['distance' => ['value' => 1000, 'text' => '1.0 km'], 'duration' => [...]]
     *     ]]
     *   ]
     * ]
     *
     * @throws GoogleMapsApiException
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
     * @param  array  $origin  ['lat' => ..., 'lng' => ...]
     * @param  array  $destinations
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
     * @param  array  $origins
     * @param  array  $destinations
     * @param  array  $options
     * @return array
     *
     * @throws GoogleMapsApiException
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

        $timeout = config('google-maps.timeout', 30);
        $response = Http::timeout($timeout)->get($this->baseUrl, $params);

        if (! $response->successful()) {
            throw GoogleMapsApiException::requestFailed(
                'Failed to call Distance Matrix API',
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
    }

    /**
     * Parse Distance Matrix API response.
     *
     * @param  array  $response
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
     * @param  array  $coordinates
     * @return string "31.9,35.9|31.8,35.8"
     */
    protected function buildCoordinatesString(array $coordinates): string
    {
        return collect($coordinates)
            ->map(fn ($c) => "{$c['lat']},{$c['lng']}")
            ->implode('|');
    }

    /**
     * Generate cache key.
     *
     * @param  array  $origins
     * @param  array  $destinations
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
     * @param  array  $origins
     * @param  array  $destinations
     *
     * @throws GoogleMapsApiException
     */
    protected function validateInputs(array $origins, array $destinations): void
    {
        if (empty($origins) || empty($destinations)) {
            throw GoogleMapsApiException::invalidCoordinates('Origins and destinations cannot be empty');
        }

        $maxOrigins = config('google-maps.distance_matrix.max_origins', 25);
        $maxDestinations = config('google-maps.distance_matrix.max_destinations', 25);

        if (count($origins) > $maxOrigins) {
            throw GoogleMapsApiException::tooManyWaypoints(count($origins), $maxOrigins);
        }

        if (count($destinations) > $maxDestinations) {
            throw GoogleMapsApiException::tooManyWaypoints(count($destinations), $maxDestinations);
        }
    }

    /**
     * Log API usage.
     *
     * @param  string  $apiType
     * @param  int  $elements
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
