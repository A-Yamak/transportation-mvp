<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GoogleMaps;

use App\Services\GoogleMaps\RouteOptimizer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('unit')]
#[Group('services')]
#[Group('google-maps')]
class RouteOptimizerTest extends TestCase
{
    private RouteOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['google-maps.api_key' => 'test_key']);
        $this->optimizer = new RouteOptimizer();
    }

    #[Test]
    public function builds_waypoints_string_correctly(): void
    {
        $destinations = [
            ['lat' => 31.9539, 'lng' => 35.9106],
            ['lat' => 32.0667, 'lng' => 36.1000],
            ['lat' => 32.5500, 'lng' => 35.8500],
        ];

        $result = $this->invokeProtectedMethod('buildWaypointsString', [$destinations]);

        $this->assertEquals('31.9539,35.9106|32.0667,36.1|32.55,35.85', $result);
    }

    #[Test]
    public function generates_consistent_cache_key(): void
    {
        $destinations = [
            ['lat' => 31.95, 'lng' => 35.91],
            ['lat' => 31.96, 'lng' => 35.92],
        ];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $key1 = $this->invokeProtectedMethod('getCacheKey', [$destinations, $start]);
        $key2 = $this->invokeProtectedMethod('getCacheKey', [$destinations, $start]);

        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('google_maps:route:', $key1);
    }

    #[Test]
    public function generates_different_cache_keys_for_different_inputs(): void
    {
        $destinations1 = [['lat' => 31.95, 'lng' => 35.91]];
        $destinations2 = [['lat' => 31.96, 'lng' => 35.92]];
        $start = ['lat' => 31.9539, 'lng' => 35.9106];

        $key1 = $this->invokeProtectedMethod('getCacheKey', [$destinations1, $start]);
        $key2 = $this->invokeProtectedMethod('getCacheKey', [$destinations2, $start]);

        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function parses_legs_correctly(): void
    {
        $legs = [
            [
                'start_address' => 'Amman, Jordan',
                'end_address' => 'Zarqa, Jordan',
                'distance' => ['value' => 25000, 'text' => '25 km'],
                'duration' => ['value' => 1200, 'text' => '20 mins'],
            ],
            [
                'start_address' => 'Zarqa, Jordan',
                'end_address' => 'Irbid, Jordan',
                'distance' => ['value' => 60000, 'text' => '60 km'],
                'duration' => ['value' => 3000, 'text' => '50 mins'],
            ],
        ];

        $result = $this->invokeProtectedMethod('parseLegs', [$legs]);

        $this->assertCount(2, $result);
        $this->assertEquals('Amman, Jordan', $result[0]['start_address']);
        $this->assertEquals('Zarqa, Jordan', $result[0]['end_address']);
        $this->assertEquals(25000, $result[0]['distance_meters']);
        $this->assertEquals('25 km', $result[0]['distance_text']);
        $this->assertEquals(1200, $result[0]['duration_seconds']);
        $this->assertEquals('20 mins', $result[0]['duration_text']);
    }

    #[Test]
    public function parses_directions_response_correctly(): void
    {
        $response = [
            'status' => 'OK',
            'routes' => [
                [
                    'waypoint_order' => [1, 0, 2],
                    'overview_polyline' => ['points' => 'encoded_polyline_123'],
                    'legs' => [
                        [
                            'start_address' => 'Start',
                            'end_address' => 'Dest 1',
                            'distance' => ['value' => 5000, 'text' => '5 km'],
                            'duration' => ['value' => 600, 'text' => '10 mins'],
                        ],
                        [
                            'start_address' => 'Dest 1',
                            'end_address' => 'Dest 2',
                            'distance' => ['value' => 3000, 'text' => '3 km'],
                            'duration' => ['value' => 400, 'text' => '7 mins'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokeProtectedMethod('parseDirectionsResponse', [$response]);

        $this->assertEquals([1, 0, 2], $result['optimized_order']);
        $this->assertEquals(8000, $result['total_distance_meters']);
        $this->assertEquals(8.0, $result['total_distance_km']);
        $this->assertEquals(1000, $result['total_duration_seconds']);
        $this->assertEquals(17, $result['total_duration_minutes']);
        $this->assertEquals('encoded_polyline_123', $result['polyline']);
        $this->assertCount(2, $result['legs']);
    }

    #[Test]
    public function handles_empty_legs_in_response(): void
    {
        $response = [
            'status' => 'OK',
            'routes' => [
                [
                    'waypoint_order' => [],
                    'overview_polyline' => ['points' => ''],
                    'legs' => [],
                ],
            ],
        ];

        $result = $this->invokeProtectedMethod('parseDirectionsResponse', [$response]);

        $this->assertEquals(0, $result['total_distance_meters']);
        $this->assertEquals(0.0, $result['total_distance_km']);
        $this->assertEquals(0, $result['total_duration_seconds']);
        $this->assertEquals(0, $result['total_duration_minutes']);
        $this->assertCount(0, $result['legs']);
    }

    /**
     * Invoke a protected method for testing.
     */
    private function invokeProtectedMethod(string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($this->optimizer);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->optimizer, $parameters);
    }
}
