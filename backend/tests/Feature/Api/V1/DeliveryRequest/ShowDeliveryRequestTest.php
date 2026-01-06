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
class ShowDeliveryRequestTest extends TestCase
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
    public function shows_delivery_request_with_destinations(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create([
                'total_km' => 25.5,
                'estimated_cost' => 14.75,
            ]);

        Destination::factory()->count(3)->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$deliveryRequest->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'status_label',
                    'total_km',
                    'estimated_cost',
                    'destination_count',
                    'completed_count',
                    'optimized_route',
                    'requested_at',
                    'destinations' => [
                        '*' => [
                            'id',
                            'external_id',
                            'address',
                            'lat',
                            'lng',
                            'sequence_order',
                            'status',
                            'navigation_url',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.id', $deliveryRequest->id)
            ->assertJsonCount(3, 'data.destinations');
    }

    #[Test]
    public function returns_404_for_other_business_request(): void
    {
        $otherBusiness = Business::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()
            ->for($otherBusiness)
            ->create();

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$deliveryRequest->id}");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Delivery request not found']);
    }

    #[Test]
    public function returns_404_for_nonexistent_request(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->apiRequest('GET', "/api/v1/delivery-requests/{$fakeId}");

        $response->assertStatus(404);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create();

        $response = $this->getJson("/api/v1/delivery-requests/{$deliveryRequest->id}");

        $response->assertStatus(401);
    }
}
