<?php

declare(strict_types=1);

namespace Tests\Feature\Services\GoogleMaps;

use App\Exceptions\GoogleMapsApiException;
use App\Services\GoogleMaps\DistanceCalculator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('services')]
#[Group('google-maps')]
#[Group('distance-calculator')]
class DistanceCalculatorTest extends TestCase
{
    private DistanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google-maps.api_key' => 'test_api_key',
            'google-maps.cache.enabled' => true,
        ]);

        $this->calculator = new DistanceCalculator();
    }

    #[Test]
    public function calculates_distance_matrix(): void
    {
        Http::fake([
            'maps.googleapis.com/maps/api/distancematrix/*' => Http::response([
                'status' => 'OK',
                'origin_addresses' => ['Amman, Jordan'],
                'destination_addresses' => ['Zarqa, Jordan', 'Irbid, Jordan'],
                'rows' => [
                    [
                        'elements' => [
                            ['distance' => ['value' => 25000, 'text' => '25 km'], 'duration' => ['value' => 1200, 'text' => '20 mins']],
                            ['distance' => ['value' => 85000, 'text' => '85 km'], 'duration' => ['value' => 3600, 'text' => '60 mins']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $origins = [['lat' => 31.9539, 'lng' => 35.9106]];
        $destinations = [
            ['lat' => 32.0667, 'lng' => 36.1000],
            ['lat' => 32.5500, 'lng' => 35.8500],
        ];

        $result = $this->calculator->calculate($origins, $destinations);

        $this->assertArrayHasKey('origin_addresses', $result);
        $this->assertArrayHasKey('destination_addresses', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(1, $result['rows']);
        $this->assertCount(2, $result['rows'][0]['elements']);
        $this->assertEquals(25000, $result['rows'][0]['elements'][0]['distance']['value']);
    }

    #[Test]
    public function calculates_total_distance_from_origin(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'origin_addresses' => ['Start'],
                'destination_addresses' => ['Dest 1', 'Dest 2', 'Dest 3'],
                'rows' => [
                    [
                        'elements' => [
                            ['distance' => ['value' => 5000]],
                            ['distance' => ['value' => 8000]],
                            ['distance' => ['value' => 12000]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $origin = ['lat' => 31.9539, 'lng' => 35.9106];
        $destinations = [
            ['lat' => 31.95, 'lng' => 35.91],
            ['lat' => 31.96, 'lng' => 35.92],
            ['lat' => 31.97, 'lng' => 35.93],
        ];

        $totalDistance = $this->calculator->calculateTotalDistance($origin, $destinations);

        $this->assertEquals(25000, $totalDistance);
    }

    #[Test]
    public function caches_distance_matrix_results(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'origin_addresses' => ['Amman'],
                'destination_addresses' => ['Zarqa'],
                'rows' => [
                    ['elements' => [['distance' => ['value' => 25000]]]],
                ],
            ], 200),
        ]);

        $origins = [['lat' => 31.9539, 'lng' => 35.9106]];
        $destinations = [['lat' => 32.0667, 'lng' => 36.1000]];

        // First call
        $this->calculator->calculate($origins, $destinations);
        Http::assertSentCount(1);

        // Second call - should use cache
        $this->calculator->calculate($origins, $destinations);
        Http::assertSentCount(1); // No additional API call
    }

    #[Test]
    public function throws_exception_when_api_key_missing(): void
    {
        config(['google-maps.api_key' => null]);

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('API key is missing');

        new DistanceCalculator();
    }

    #[Test]
    public function handles_api_errors(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'INVALID_REQUEST',
                'error_message' => 'Invalid request parameters',
            ], 200),
        ]);

        $origins = [['lat' => 31.9539, 'lng' => 35.9106]];
        $destinations = [['lat' => 32.0667, 'lng' => 36.1000]];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('INVALID_REQUEST');

        $this->calculator->calculate($origins, $destinations);
    }

    #[Test]
    public function validates_origins_not_empty(): void
    {
        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Origins and destinations cannot be empty');

        $this->calculator->calculate([], [['lat' => 32.0667, 'lng' => 36.1000]]);
    }

    #[Test]
    public function validates_destinations_not_empty(): void
    {
        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Origins and destinations cannot be empty');

        $this->calculator->calculate([['lat' => 31.9539, 'lng' => 35.9106]], []);
    }

    #[Test]
    public function validates_max_origins_limit(): void
    {
        $origins = array_fill(0, 30, ['lat' => 31.95, 'lng' => 35.91]);
        $destinations = [['lat' => 32.0667, 'lng' => 36.1000]];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('waypoints');

        $this->calculator->calculate($origins, $destinations);
    }

    #[Test]
    public function validates_max_destinations_limit(): void
    {
        $origins = [['lat' => 31.9539, 'lng' => 35.9106]];
        $destinations = array_fill(0, 30, ['lat' => 31.95, 'lng' => 35.91]);

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('waypoints');

        $this->calculator->calculate($origins, $destinations);
    }

    #[Test]
    public function handles_http_failures(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $origins = [['lat' => 31.9539, 'lng' => 35.9106]];
        $destinations = [['lat' => 32.0667, 'lng' => 36.1000]];

        $this->expectException(GoogleMapsApiException::class);
        $this->expectExceptionMessage('Failed to call Distance Matrix API');

        $this->calculator->calculate($origins, $destinations);
    }

    #[Test]
    public function supports_batch_processing(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'origin_addresses' => ['Amman 1', 'Amman 2'],
                'destination_addresses' => ['Zarqa 1', 'Zarqa 2', 'Zarqa 3'],
                'rows' => [
                    ['elements' => [
                        ['distance' => ['value' => 1000]],
                        ['distance' => ['value' => 2000]],
                        ['distance' => ['value' => 3000]],
                    ]],
                    ['elements' => [
                        ['distance' => ['value' => 1500]],
                        ['distance' => ['value' => 2500]],
                        ['distance' => ['value' => 3500]],
                    ]],
                ],
            ], 200),
        ]);

        $origins = [
            ['lat' => 31.95, 'lng' => 35.91],
            ['lat' => 31.96, 'lng' => 35.92],
        ];

        $destinations = [
            ['lat' => 32.06, 'lng' => 36.10],
            ['lat' => 32.07, 'lng' => 36.11],
            ['lat' => 32.08, 'lng' => 36.12],
        ];

        $result = $this->calculator->calculate($origins, $destinations);

        $this->assertCount(2, $result['rows']);
        $this->assertCount(3, $result['rows'][0]['elements']);
        $this->assertCount(3, $result['rows'][1]['elements']);
    }
}
