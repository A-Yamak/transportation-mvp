<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\DeliveryRequest;

use App\Enums\DeliveryRequestStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('delivery-requests')]
#[Group('api')]
class IndexDeliveryRequestTest extends TestCase
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
    public function lists_business_delivery_requests(): void
    {
        // Create delivery requests for this business
        $requests = DeliveryRequest::factory()
            ->count(3)
            ->for($this->business)
            ->create();

        // Add destinations to each request
        foreach ($requests as $request) {
            Destination::factory()->count(2)->create([
                'delivery_request_id' => $request->id,
            ]);
        }

        $response = $this->apiRequest('GET', '/api/v1/delivery-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'total_km',
                        'estimated_cost',
                        'destinations',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'links',
            ])
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function filters_by_status(): void
    {
        // Create requests with different statuses
        DeliveryRequest::factory()
            ->count(2)
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Pending]);

        DeliveryRequest::factory()
            ->count(3)
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Completed]);

        // Filter by pending status
        $response = $this->apiRequest('GET', '/api/v1/delivery-requests?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Filter by completed status
        $response = $this->apiRequest('GET', '/api/v1/delivery-requests?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function paginates_results(): void
    {
        // Create 20 delivery requests
        DeliveryRequest::factory()
            ->count(20)
            ->for($this->business)
            ->create();

        // Request first page with 5 items
        $response = $this->apiRequest('GET', '/api/v1/delivery-requests?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 4);
    }

    #[Test]
    public function only_shows_own_business_requests(): void
    {
        // Create requests for this business
        DeliveryRequest::factory()
            ->count(2)
            ->for($this->business)
            ->create();

        // Create requests for another business
        $otherBusiness = Business::factory()->create();
        DeliveryRequest::factory()
            ->count(3)
            ->for($otherBusiness)
            ->create();

        $response = $this->apiRequest('GET', '/api/v1/delivery-requests');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function orders_by_created_at_descending(): void
    {
        // Create requests with specific creation times
        $oldest = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['created_at' => now()->subDays(2)]);

        $newest = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['created_at' => now()]);

        $middle = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['created_at' => now()->subDay()]);

        $response = $this->apiRequest('GET', '/api/v1/delivery-requests');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertEquals([$newest->id, $middle->id, $oldest->id], $ids);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/delivery-requests');

        $response->assertStatus(401);
    }

    #[Test]
    public function limits_max_per_page(): void
    {
        DeliveryRequest::factory()
            ->count(150)
            ->for($this->business)
            ->create();

        // Request with per_page over limit (100)
        $response = $this->apiRequest('GET', '/api/v1/delivery-requests?per_page=200');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }
}
