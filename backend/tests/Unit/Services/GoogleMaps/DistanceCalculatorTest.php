<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GoogleMaps;

use App\Services\GoogleMaps\DistanceCalculator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('unit')]
#[Group('services')]
#[Group('google-maps')]
class DistanceCalculatorTest extends TestCase
{
    private DistanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        config(['google-maps.api_key' => 'test_key']);
        $this->calculator = new DistanceCalculator();
    }

    #[Test]
    public function builds_coordinates_string_correctly(): void
    {
        $coordinates = [
            ['lat' => 31.9539, 'lng' => 35.9106],
            ['lat' => 32.0667, 'lng' => 36.1000],
            ['lat' => 32.5500, 'lng' => 35.8500],
        ];

        $result = $this->invokeProtectedMethod('buildCoordinatesString', [$coordinates]);

        $this->assertEquals('31.9539,35.9106|32.0667,36.1|32.55,35.85', $result);
    }

    #[Test]
    public function builds_coordinates_string_with_single_coordinate(): void
    {
        $coordinates = [
            ['lat' => 31.9539, 'lng' => 35.9106],
        ];

        $result = $this->invokeProtectedMethod('buildCoordinatesString', [$coordinates]);

        $this->assertEquals('31.9539,35.9106', $result);
    }

    #[Test]
    public function generates_consistent_cache_key(): void
    {
        $origins = [['lat' => 31.9539, 'lng' => 35.9106]];
        $destinations = [
            ['lat' => 32.0667, 'lng' => 36.1000],
            ['lat' => 32.5500, 'lng' => 35.8500],
        ];

        $key1 = $this->invokeProtectedMethod('getCacheKey', [$origins, $destinations]);
        $key2 = $this->invokeProtectedMethod('getCacheKey', [$origins, $destinations]);

        $this->assertEquals($key1, $key2);
        $this->assertStringStartsWith('google_maps:distance:', $key1);
    }

    #[Test]
    public function generates_different_cache_keys_for_different_inputs(): void
    {
        $origins1 = [['lat' => 31.9539, 'lng' => 35.9106]];
        $origins2 = [['lat' => 32.0667, 'lng' => 36.1000]];
        $destinations = [['lat' => 32.5500, 'lng' => 35.8500]];

        $key1 = $this->invokeProtectedMethod('getCacheKey', [$origins1, $destinations]);
        $key2 = $this->invokeProtectedMethod('getCacheKey', [$origins2, $destinations]);

        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function parses_distance_matrix_response(): void
    {
        $response = [
            'status' => 'OK',
            'origin_addresses' => ['Amman, Jordan', 'Zarqa, Jordan'],
            'destination_addresses' => ['Irbid, Jordan', 'Aqaba, Jordan'],
            'rows' => [
                [
                    'elements' => [
                        ['distance' => ['value' => 85000, 'text' => '85 km']],
                        ['distance' => ['value' => 330000, 'text' => '330 km']],
                    ],
                ],
                [
                    'elements' => [
                        ['distance' => ['value' => 60000, 'text' => '60 km']],
                        ['distance' => ['value' => 305000, 'text' => '305 km']],
                    ],
                ],
            ],
        ];

        $result = $this->invokeProtectedMethod('parseDistanceMatrixResponse', [$response]);

        $this->assertArrayHasKey('origin_addresses', $result);
        $this->assertArrayHasKey('destination_addresses', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(2, $result['origin_addresses']);
        $this->assertCount(2, $result['destination_addresses']);
        $this->assertCount(2, $result['rows']);
    }

    #[Test]
    public function handles_empty_response_gracefully(): void
    {
        $response = [
            'status' => 'OK',
        ];

        $result = $this->invokeProtectedMethod('parseDistanceMatrixResponse', [$response]);

        $this->assertArrayHasKey('origin_addresses', $result);
        $this->assertArrayHasKey('destination_addresses', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(0, $result['origin_addresses']);
        $this->assertCount(0, $result['destination_addresses']);
        $this->assertCount(0, $result['rows']);
    }

    /**
     * Invoke a protected method for testing.
     */
    private function invokeProtectedMethod(string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($this->calculator);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->calculator, $parameters);
    }
}
