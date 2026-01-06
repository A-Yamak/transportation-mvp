<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\DeliveryRequest;

use App\Enums\DeliveryRequestStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('delivery-requests')]
#[Group('api')]
class CancelDeliveryRequestTest extends TestCase
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
    public function cancels_pending_delivery_request(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Pending]);

        $response = $this->apiRequest('POST', "/api/v1/delivery-requests/{$deliveryRequest->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => DeliveryRequestStatus::Cancelled->value,
        ]);
    }

    #[Test]
    public function cannot_cancel_in_progress_request(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::InProgress]);

        $response = $this->apiRequest('POST', "/api/v1/delivery-requests/{$deliveryRequest->id}/cancel");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only pending delivery requests can be cancelled.']);

        // Verify status unchanged
        $this->assertDatabaseHas('delivery_requests', [
            'id' => $deliveryRequest->id,
            'status' => DeliveryRequestStatus::InProgress->value,
        ]);
    }

    #[Test]
    public function cannot_cancel_completed_request(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->completed()
            ->create();

        $response = $this->apiRequest('POST', "/api/v1/delivery-requests/{$deliveryRequest->id}/cancel");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Only pending delivery requests can be cancelled.']);
    }

    #[Test]
    public function cannot_cancel_other_business_request(): void
    {
        $otherBusiness = Business::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()
            ->for($otherBusiness)
            ->create(['status' => DeliveryRequestStatus::Pending]);

        $response = $this->apiRequest('POST', "/api/v1/delivery-requests/{$deliveryRequest->id}/cancel");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Delivery request not found']);
    }

    #[Test]
    public function requires_authentication(): void
    {
        $deliveryRequest = DeliveryRequest::factory()
            ->for($this->business)
            ->create(['status' => DeliveryRequestStatus::Pending]);

        $response = $this->postJson("/api/v1/delivery-requests/{$deliveryRequest->id}/cancel");

        $response->assertStatus(401);
    }
}
