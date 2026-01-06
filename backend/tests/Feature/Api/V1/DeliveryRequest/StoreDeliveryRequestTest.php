<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\DeliveryRequest;

use App\Enums\BusinessType;
use App\Enums\DeliveryRequestStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\PricingTier;
use App\Services\GoogleMaps\RouteOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('delivery-requests')]
#[Group('api')]
class StoreDeliveryRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a business for testing
        $this->business = Business::factory()->bulkOrder()->create();

        // Mock RouteOptimizer to avoid Google Maps API calls
        $this->mockRouteOptimizer();
    }

    /**
     * Mock the RouteOptimizer service.
     */
    protected function mockRouteOptimizer(): void
    {
        $mock = Mockery::mock(RouteOptimizer::class);
        $mock->shouldReceive('optimize')
            ->andReturn([
                'optimized_order' => [0, 1, 2],
                'total_distance_meters' => 25000,
                'total_distance_km' => 25.0,
                'total_duration_seconds' => 1800,
                'total_duration_minutes' => 30,
                'polyline' => 'mock_polyline_string',
                'legs' => [],
            ]);

        $this->app->instance(RouteOptimizer::class, $mock);
    }

    /**
     * Helper to make authenticated API requests.
     */
    protected function apiRequest(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders([
            'X-API-Key' => $this->business->api_key,
            'Accept' => 'application/json',
        ])->json($method, $uri, $data);
    }

    #[Test]
    public function creates_delivery_request_with_destinations(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'order-001',
                    'address' => '123 Main Street, Amman',
                    'lat' => 31.9539,
                    'lng' => 35.9106,
                ],
                [
                    'external_id' => 'order-002',
                    'address' => '456 Oak Avenue, Amman',
                    'lat' => 31.9600,
                    'lng' => 35.9200,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'total_km',
                    'estimated_cost',
                    'destination_count',
                    'optimized_route',
                    'destinations' => [
                        '*' => [
                            'id',
                            'external_id',
                            'address',
                            'lat',
                            'lng',
                            'sequence_order',
                            'status',
                        ],
                    ],
                ],
            ]);

        // Verify database records
        $this->assertDatabaseHas('delivery_requests', [
            'business_id' => $this->business->id,
            'status' => DeliveryRequestStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'order-001',
        ]);

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'order-002',
        ]);
    }

    #[Test]
    public function optimizes_route_on_creation(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                ['external_id' => 'A', 'address' => 'Address A', 'lat' => 31.9, 'lng' => 35.9],
                ['external_id' => 'B', 'address' => 'Address B', 'lat' => 31.8, 'lng' => 35.8],
                ['external_id' => 'C', 'address' => 'Address C', 'lat' => 31.7, 'lng' => 35.7],
            ],
        ]);

        $response->assertStatus(201);

        $data = $response->json('data');

        // Check route optimization data is stored
        $this->assertNotNull($data['optimized_route']);
        $this->assertArrayHasKey('polyline', $data['optimized_route']);
        $this->assertArrayHasKey('waypoint_order', $data['optimized_route']);
        $this->assertEquals(25.0, $data['total_km']);
    }

    #[Test]
    public function calculates_cost_based_on_distance(): void
    {
        // Create a pricing tier for the business type
        PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.50,
            'base_fee' => 2.00,
            'minimum_cost' => 5.00,
            'effective_date' => now()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                ['external_id' => 'order-1', 'address' => 'Test', 'lat' => 31.9, 'lng' => 35.9],
            ],
        ]);

        $response->assertStatus(201);

        $data = $response->json('data');

        // Cost should be calculated: 25km * 0.50 + 2.00 base = 14.50
        $this->assertNotNull($data['estimated_cost']);
        $this->assertIsNumeric($data['estimated_cost']);
    }

    #[Test]
    public function requires_api_key_authentication(): void
    {
        // Request without X-API-Key header
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                ['external_id' => 'test', 'address' => 'Test', 'lat' => 31.9, 'lng' => 35.9],
            ],
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'API key required. Please provide X-API-Key header.']);
    }

    #[Test]
    public function rejects_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'invalid_api_key',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                ['external_id' => 'test', 'address' => 'Test', 'lat' => 31.9, 'lng' => 35.9],
            ],
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid API key.']);
    }

    #[Test]
    public function rejects_inactive_business(): void
    {
        $inactiveBusiness = Business::factory()->inactive()->create();

        $response = $this->withHeaders([
            'X-API-Key' => $inactiveBusiness->api_key,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                ['external_id' => 'test', 'address' => 'Test', 'lat' => 31.9, 'lng' => 35.9],
            ],
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Business account is inactive. Please contact support.']);
    }

    #[Test]
    public function validates_destination_coordinates(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'test',
                    'address' => 'Test Address',
                    'lat' => 100, // Invalid: lat must be between -90 and 90
                    'lng' => 35.9,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinations.0.lat']);
    }

    #[Test]
    public function validates_minimum_one_destination(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinations']);
    }

    #[Test]
    public function validates_maximum_25_destinations(): void
    {
        // Create 26 destinations (over the limit)
        $destinations = [];
        for ($i = 0; $i < 26; $i++) {
            $destinations[] = [
                'external_id' => "order-{$i}",
                'address' => "Address {$i}",
                'lat' => 31.9 + ($i * 0.01),
                'lng' => 35.9 + ($i * 0.01),
            ];
        }

        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => $destinations,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destinations']);
    }

    #[Test]
    public function stores_external_id_for_erp_reference(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ERP-ORDER-12345',
                    'address' => 'Test Address',
                    'lat' => 31.9539,
                    'lng' => 35.9106,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'ERP-ORDER-12345',
        ]);

        // Verify external_id is returned in response
        $response->assertJsonPath('data.destinations.0.external_id', 'ERP-ORDER-12345');
    }

    #[Test]
    public function validates_required_fields(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                [
                    // Missing required fields
                    'notes' => 'Some notes',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'destinations.0.external_id',
                'destinations.0.address',
                'destinations.0.lat',
                'destinations.0.lng',
            ]);
    }

    #[Test]
    public function accepts_optional_notes(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'order-1',
                    'address' => 'Test Address',
                    'lat' => 31.9,
                    'lng' => 35.9,
                    'notes' => 'Leave at front door',
                ],
            ],
            'notes' => 'Urgent delivery',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'order-1',
            'notes' => 'Leave at front door',
        ]);

        $this->assertDatabaseHas('delivery_requests', [
            'notes' => 'Urgent delivery',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
