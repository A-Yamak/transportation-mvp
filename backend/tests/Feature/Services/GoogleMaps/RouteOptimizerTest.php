<?php

declare(strict_types=1);

namespace Tests\Feature\Services\GoogleMaps;

use App\Exceptions\GoogleMapsApiException;
use App\Services\GoogleMaps\RouteOptimizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('services')]
#[Group('google-maps')]
#[Group('route-optimizer')]
class RouteOptimizerTest extends TestCase
{
    private RouteOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google-maps.api_key' => 'test_api_key',
            'google-maps.cache.enabled' => true,
            'google-maps.cache.ttl' => 900,
        ]);

        Cache::flush();
    }

    protected function createOptimizer(): RouteOptimizer
    {
        return new RouteOptimizer();
    }

    #[Test]
    public function optimizes_route_with_multiple_destinations(): void
    {
        Http::fake([
            'maps.googleapis.com/maps/api/directions/*' => Http::response([
                'status' => 'OK',
                'routes' => [
                    [
                        'waypoint_order' => [1, 0, 2],
                        'legs' => [
                            ['distance' => ['value' => 5000], 'duration' => ['value' => 600]],
                            ['distance' => ['value' => 3000], 'duration' => ['value' => 400]],
                            ['distance' => ['value' => 2000], 'duration' => ['value' => 300]],
                        ],
                        'overview_polyline' => ['points' => 'test_polyline_abc123'],
                    ],
                ],
            ], 200),
        ]);

        $destinations = [
            ['lat' => 31.95, 'lng' => 35.91],
            ['lat' => 31.96, 'lng' => 35.92],
            ['lat' => 31.97, 'lng' => 35.93],
        ];

        $startPoint = ['lat' => 31.9539, 'lng' => 35.9106];

        $optimizer = $this->createOptimizer();
        $result = $optimizer->optimize($destinations, $startPoint);

        $this->assertEquals([1, 0, 2], $result['optimized_order']);
        $this->assertEquals(10000, $result['total_distance_meters']);
        $this->assertEquals(10.0, $result['total_distance_km']);
        $this->assertEquals(1300, $result['total_duration_seconds']);
        $this->assertEquals(22, $result['total_duration_minutes']);
        $this->assertEquals('test_polyline_abc123', $result['polyline']);
        $this->assertCount(3, $result['legs']);
    }

    #[Test]
    public function caches_optimization_results(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [
                    [
                        'waypoint_order' => [0],
                        'legs' => [
                            ['distance' => ['value' => 1000], 'duration' => ['value' => 100]],
                        ],
                        'overview_polyline' => ['points' => 'test'],
                    ],
                ],
            ], 200),
        ]);

        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        // First call - should hit API
        $this->optimizer->optimize($destinations, $start);
        Http::assertSentCount(1);

        // Second call with same data - should use cache
        $this->optimizer->optimize($destinations, $start);
        Http::assertSentCount(1); // No additional API call
    }

    #[Test]
    public function throws_exception_when_api_key_missing(): void
    {
        config(['google-maps.api_key' => null]);

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Google Maps API key is missing or invalid');

        new RouteOptimizer();
    }

    #[Test]
    public function handles_google_maps_api_errors(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'error_message' => 'The provided API key is invalid',
            ], 200),
        ]);

        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('REQUEST_DENIED');

        $this->optimizer->optimize($destinations, $start);
    }

    #[Test]
    public function validates_destination_count_limit(): void
    {
        $destinations = array_fill(0, 30, ['lat' => 31.95, 'lng' => 35.91]);
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Cannot optimize 30 waypoints');

        $this->optimizer->optimize($destinations, $start);
    }

    #[Test]
    public function validates_destinations_have_coordinates(): void
    {
        $destinations = [
            ['lat' => 31.95, 'lng' => 35.91],
            ['address' => '123 Main St'], // Missing lat/lng
        ];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('missing lat or lng');

        $this->optimizer->optimize($destinations, $start);
    }

    #[Test]
    public function validates_start_point_has_coordinates(): void
    {
        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['address' => 'Depot']; // Missing lat/lng

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Start point must have lat and lng');

        $this->optimizer->optimize($destinations, $start);
    }

    #[Test]
    public function retries_on_network_failure(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::sequence()
                ->push(['error' => 'Network error'], 500)
                ->push(['error' => 'Network error'], 500)
                ->push([
                    'status' => 'OK',
                    'routes' => [
                        [
                            'waypoint_order' => [0],
                            'legs' => [
                                ['distance' => ['value' => 1000], 'duration' => ['value' => 100]],
                            ],
                            'overview_polyline' => ['points' => 'test'],
                        ],
                    ],
                ], 200),
        ]);

        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $result = $this->optimizer->optimize($destinations, $start);

        $this->assertEquals([0], $result['optimized_order']);
        Http::assertSentCount(3); // Should retry 3 times total
    }

    #[Test]
    public function throws_exception_after_max_retry_attempts(): void
    {
        config(['google-maps.retry.max_attempts' => 2]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Failed after 2 attempts');

        $this->optimizer->optimize($destinations, $start);
    }

    #[Test]
    public function throws_exception_when_no_route_found(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [], // No routes
            ], 200),
        ]);

        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('No route found');

        $this->optimizer->optimize($destinations, $start);
    }

    #[Test]
    public function clears_cache_when_disabled(): void
    {
        config(['google-maps.cache.enabled' => false]);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'routes' => [
                    [
                        'waypoint_order' => [0],
                        'legs' => [
                            ['distance' => ['value' => 1000], 'duration' => ['value' => 100]],
                        ],
                        'overview_polyline' => ['points' => 'test'],
                    ],
                ],
            ], 200),
        ]);

        $optimizer = new RouteOptimizer();
        $destinations = [['lat' => 31.95, 'lng' => 35.91]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        // First call
        $optimizer->optimize($destinations, $start);
        Http::assertSentCount(1);

        // Second call - should hit API again (no cache)
        $optimizer->optimize($destinations, $start);
        Http::assertSentCount(2);
    }
}
