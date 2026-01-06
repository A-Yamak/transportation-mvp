<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\DeliveryRequest;

use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('delivery-requests')]
#[Group('api')]
class RouteDeliveryRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Business::factory()->bulkOrder()->create();
    }

    protected function apiRequest(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders([
            'X-API-Key' => $this->business->api_key,
            'Accept' => 'application/json',
        ])->json($method, $uri, $data);
    }

    #[Test]
    public function returns_route_data_for_delivery_request(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->withRoute()
            ->create([
                'total_km' => 25.5,
            ]);

        Destination::factory()->count(3)->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$deliveryRequest->id}/route");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'polyline',
                    'waypoint_order',
                    'total_km',
                    'total_duration_minutes',
                    'factory_location' => ['lat', 'lng'],
                    'destinations' => [
                        '*' => [
                            'id',
                            'external_id',
                            'address',
                            'lat',
                            'lng',
                            'sequence_order',
                            'navigation_url',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.id', $deliveryRequest->id)
            ->assertJsonCount(3, 'data.destinations');

        // Check total_km separately due to decimal casting returning string
        $this->assertEquals(25.5, (float) $response->json('data.total_km'));
    }

    #[Test]
    public function returns_factory_location(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create();

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$deliveryRequest->id}/route");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'factory_location' => ['lat', 'lng'],
                ],
            ]);

        $factoryLocation = $response->json('data.factory_location');
        $this->assertIsFloat($factoryLocation['lat']);
        $this->assertIsFloat($factoryLocation['lng']);
    }

    #[Test]
    public function returns_404_for_other_business_request(): void
    {
        $otherBusiness = Business::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()
            ->for($otherBusiness)
            ->create();

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$deliveryRequest->id}/route");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Delivery request not found']);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create();

        $response = $this->getJson("/api/v1/delivery-requests/{$deliveryRequest->id}/route");

        $response->assertStatus(401);
    }

    #[Test]
    public function destinations_include_navigation_urls(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create();

        Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'lat' => 31.9539,
            'lng' => 35.9106,
        ]);

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$deliveryRequest->id}/route");

        $response->assertStatus(200);

        $destination = $response->json('data.destinations.0');
        $this->assertStringContains('google.com/maps', $destination['navigation_url']);
        $this->assertStringContains('31.9539', $destination['navigation_url']);
        $this->assertStringContains('35.9106', $destination['navigation_url']);
    }

    /**
     * Assert that a string contains a substring.
     */
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
