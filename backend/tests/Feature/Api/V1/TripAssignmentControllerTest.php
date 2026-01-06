<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\DeliveryRequestStatus;
use App\Enums\TripStatus;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('api')]
#[Group('trip-assignment')]
class TripAssignmentControllerTest extends TestCase
{
    private User $user;
    private Driver $driver;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user (admin/dispatcher)
        $this->user = $this->createAuthenticatedUser();

        // Create an active driver with vehicle
        $this->vehicle = Vehicle::factory()->create(['is_active' => true]);
        $this->driver = Driver::factory()->withVehicle($this->vehicle)->create();
    }

    #[Test]
    public function can_assign_delivery_request_to_driver(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::Pending,
        ]);

        $response = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'delivery_request',
                    'destinations',
                ],
            ])
            ->assertJsonPath('data.status', TripStatus::NotStarted->value);

        $this->assertDatabaseHas('trips', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => TripStatus::NotStarted->value,
        ]);

        // Delivery request status should be updated
        $deliveryRequest->refresh();
        $this->assertEquals(DeliveryRequestStatus::Accepted, $deliveryRequest->status);
    }

    #[Test]
    public function cannot_assign_already_assigned_delivery_request(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();

        // Create existing trip
        Trip::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Delivery request already assigned to a trip');
    }

    #[Test]
    public function cannot_assign_to_inactive_driver(): void
    {
        $inactiveDriver = Driver::factory()->inactive()->create();
        $deliveryRequest = DeliveryRequest::factory()->create();

        $response = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $inactiveDriver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot assign to inactive driver');
    }

    #[Test]
    public function cannot_assign_to_inactive_vehicle(): void
    {
        $inactiveVehicle = Vehicle::factory()->create(['is_active' => false]);
        $deliveryRequest = DeliveryRequest::factory()->create();

        $response = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $inactiveVehicle->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot assign to inactive vehicle');
    }

    #[Test]
    public function can_list_unassigned_delivery_requests(): void
    {
        // Create pending delivery requests without trips
        $unassigned1 = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::Pending,
        ]);
        $unassigned2 = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::Pending,
        ]);

        // Create an assigned delivery request (should not appear)
        $assigned = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::Accepted,
        ]);
        Trip::factory()->create([
            'delivery_request_id' => $assigned->id,
        ]);

        $response = $this->getJson('/api/v1/trips/unassigned');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links',
            ]);

        // Should only contain unassigned requests
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($unassigned1->id, $ids);
        $this->assertContains($unassigned2->id, $ids);
        $this->assertNotContains($assigned->id, $ids);
    }

    #[Test]
    public function can_list_available_drivers(): void
    {
        // Create additional active driver with vehicle
        $driver2 = Driver::factory()->withVehicle()->create();

        // Create inactive driver (should not appear)
        Driver::factory()->inactive()->create();

        // Create driver without vehicle (should not appear)
        Driver::factory()->create(['vehicle_id' => null]);

        $response = $this->getJson('/api/v1/trips/available-drivers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'phone',
                        'vehicle',
                    ],
                ],
            ]);

        // Should contain active drivers with vehicles
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->driver->id, $ids);
        $this->assertContains($driver2->id, $ids);
    }

    #[Test]
    public function validates_required_fields_for_assignment(): void
    {
        $response = $this->postJson('/api/v1/trips/assign', []);

        $this->assertValidationError($response, [
            'delivery_request_id',
            'driver_id',
            'vehicle_id',
        ]);
    }

    #[Test]
    public function validates_uuid_format_for_assignment(): void
    {
        $response = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => 'not-a-uuid',
            'driver_id' => 'invalid',
            'vehicle_id' => '123',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_trip_assignment(): void
    {
        // Reset authentication
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/trips/unassigned');

        $this->assertUnauthorized($response);
    }
}
