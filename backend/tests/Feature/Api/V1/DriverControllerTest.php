<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\DestinationStatus;
use App\Enums\TripStatus;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('api')]
#[Group('driver')]
class DriverControllerTest extends TestCase
{
    private Driver $driver;
    private User $user;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create driver with vehicle and user
        $this->vehicle = Vehicle::factory()->create();
        $this->driver = Driver::factory()->withVehicle($this->vehicle)->create();
        $this->user = $this->driver->user;
    }

    #[Test]
    public function driver_can_get_todays_trips(): void
    {
        // Create trips for today
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->today()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        // Create destinations for the delivery request
        Destination::factory()->count(3)->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/driver/trips/today');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'started_at',
                        'completed_at',
                        'delivery_request',
                        'destinations',
                        'progress',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.id', $trip->id);
    }

    #[Test]
    public function driver_can_view_trip_details(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        Destination::factory()->count(2)->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/driver/trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'delivery_request',
                    'destinations',
                    'vehicle',
                    'progress',
                ],
            ])
            ->assertJsonPath('data.id', $trip->id);
    }

    #[Test]
    public function driver_can_start_trip(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::NotStarted,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/driver/trips/{$trip->id}/start", [
                'lat' => 31.9539,
                'lng' => 35.9106,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TripStatus::InProgress->value);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => TripStatus::InProgress->value,
        ]);

        $trip->refresh();
        $this->assertNotNull($trip->started_at);
    }

    #[Test]
    public function driver_cannot_start_already_started_trip(): void
    {
        $trip = Trip::factory()->inProgress()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/driver/trips/{$trip->id}/start");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Trip cannot be started - current status: ' . TripStatus::InProgress->value);
    }

    #[Test]
    public function driver_can_arrive_at_destination(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->inProgress()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $destination = Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'status' => DestinationStatus::Pending,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/arrive", [
                'lat' => 31.9540,
                'lng' => 35.9110,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DestinationStatus::Arrived->value);

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'status' => DestinationStatus::Arrived->value,
        ]);

        $destination->refresh();
        $this->assertNotNull($destination->arrived_at);
    }

    #[Test]
    public function driver_can_complete_destination(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->inProgress()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $destination = Destination::factory()->arrived()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/complete", [
                'recipient_name' => 'Ahmad Hassan',
                'notes' => 'Left at reception',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DestinationStatus::Completed->value);

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'status' => DestinationStatus::Completed->value,
            'recipient_name' => 'Ahmad Hassan',
        ]);

        $destination->refresh();
        $this->assertNotNull($destination->completed_at);
    }

    #[Test]
    public function driver_cannot_complete_trip_with_pending_destinations(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->inProgress()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        // Create one completed and one pending destination
        Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);
        Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'status' => DestinationStatus::Pending,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/driver/trips/{$trip->id}/complete", [
                'total_km' => 45.5,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot complete trip - 1 destinations not completed']);
    }

    #[Test]
    public function driver_can_complete_trip_when_all_destinations_done(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->inProgress()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        // Create only completed destinations
        Destination::factory()->count(3)->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $initialVehicleKm = $this->vehicle->total_km_driven;

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/driver/trips/{$trip->id}/complete", [
                'total_km' => 45.5,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', TripStatus::Completed->value);

        $trip->refresh();
        $this->assertEquals(TripStatus::Completed, $trip->status);
        $this->assertNotNull($trip->completed_at);
        $this->assertEquals(45.5, (float) $trip->actual_km);

        // Vehicle KM should be updated
        $this->vehicle->refresh();
        $this->assertEquals($initialVehicleKm + 45.5, (float) $this->vehicle->total_km_driven);
    }

    #[Test]
    public function driver_gets_navigation_url(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create();
        $trip = Trip::factory()->inProgress()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $destination = Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'lat' => 31.9539,
            'lng' => 35.9106,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/navigate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'url',
                    'destination',
                ],
            ]);

        $this->assertStringContains('google.com/maps/dir', $response->json('data.url'));
        $this->assertStringContains('31.9539', $response->json('data.url'));
        $this->assertStringContains('35.9106', $response->json('data.url'));
    }

    #[Test]
    public function driver_cannot_access_other_drivers_trips(): void
    {
        // Create another driver's trip
        $otherDriver = Driver::factory()->create();
        $trip = Trip::factory()->create([
            'driver_id' => $otherDriver->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/driver/trips/{$trip->id}");

        $this->assertForbidden($response);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_driver_endpoints(): void
    {
        $response = $this->getJson('/api/v1/driver/trips/today');

        $this->assertUnauthorized($response);
    }

    /**
     * Helper to check if string contains substring.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
