<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Driver;

use App\Enums\DeliveryRequestStatus;
use App\Enums\DestinationStatus;
use App\Enums\TripStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Driver Trip History API Tests
 *
 * Tests for driver trip history endpoint:
 * - GET /api/v1/driver/trips/history - Get past trips with pagination
 */
class DriverTripHistoryTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // GET /api/v1/driver/trips/history
    // =========================================================================

    public function test_driver_can_get_trip_history(): void
    {
        $vehicle = Vehicle::factory()->create([
            'make' => 'Volkswagen',
            'model' => 'Caddy',
            'year' => 2019,
        ]);

        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $business = Business::factory()->create(['name' => 'Melo Group']);

        // Create trips on different dates
        $trip1 = $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subDays(1), 25.5, 3);
        $trip2 = $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subDays(5), 45.0, 5);
        $trip3 = $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subMonth(), 100.0, 10);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/trips/history');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'date',
                        'started_at',
                        'completed_at',
                        'duration_minutes',
                        'actual_km',
                        'destinations_count',
                        'destinations_completed',
                        'business_name',
                        'vehicle' => [
                            'make',
                            'model',
                            'license_plate',
                        ],
                    ],
                ],
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        // Should be ordered by date descending (most recent first)
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals($trip1->id, $data[0]['id']);
        $this->assertEquals($trip2->id, $data[1]['id']);
        $this->assertEquals($trip3->id, $data[2]['id']);
    }

    public function test_trip_history_is_paginated(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $business = Business::factory()->create();

        // Create 25 trips
        for ($i = 0; $i < 25; $i++) {
            $this->createCompletedTripWithDetails(
                $driver,
                $vehicle,
                $business,
                today()->subDays($i),
                rand(10, 100) / 1.0,
                rand(1, 5)
            );
        }

        $this->actingAs($user, 'api');

        // First page (default 15 per page)
        $response = $this->getJson('/api/v1/driver/trips/history');

        $response->assertOk()
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(15, $response->json('data'));

        // Second page
        $response = $this->getJson('/api/v1/driver/trips/history?page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2);

        $this->assertCount(10, $response->json('data'));
    }

    public function test_trip_history_can_filter_by_date_range(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $business = Business::factory()->create();

        // Create trips across different dates
        $this->createCompletedTripWithDetails($driver, $vehicle, $business, today(), 10.0, 1);
        $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subDays(3), 20.0, 2);
        $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subDays(10), 30.0, 3);
        $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subMonth(), 40.0, 4);

        $this->actingAs($user, 'api');

        // Filter last 7 days
        $response = $this->getJson('/api/v1/driver/trips/history?' . http_build_query([
            'from' => today()->subDays(7)->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('data')); // Only trips from last 7 days
    }

    public function test_trip_history_can_filter_by_status(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $business = Business::factory()->create();

        // Create completed trip
        $this->createCompletedTripWithDetails($driver, $vehicle, $business, today()->subDay(), 10.0, 1);

        // Create cancelled trip
        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
            'status' => DeliveryRequestStatus::Cancelled,
        ]);
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::Cancelled,
            'created_at' => today()->subDays(2),
        ]);

        $this->actingAs($user, 'api');

        // Filter only completed
        $response = $this->getJson('/api/v1/driver/trips/history?status=completed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('completed', $response->json('data.0.status'));

        // Filter only cancelled
        $response = $this->getJson('/api/v1/driver/trips/history?status=cancelled');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('cancelled', $response->json('data.0.status'));
    }

    public function test_trip_history_shows_destination_details(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $business = Business::factory()->create();

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
            'status' => DeliveryRequestStatus::Completed,
        ]);

        // Create 5 destinations: 4 completed, 1 failed
        for ($i = 0; $i < 4; $i++) {
            Destination::factory()->create([
                'delivery_request_id' => $deliveryRequest->id,
                'status' => DestinationStatus::Completed,
            ]);
        }
        Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'status' => DestinationStatus::Failed,
        ]);

        Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::Completed,
            'actual_km' => 30.0,
            'started_at' => today()->setHour(8),
            'completed_at' => today()->setHour(14),
            'created_at' => today(),
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/trips/history');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'destinations_count' => 5,
                        'destinations_completed' => 4,
                        'duration_minutes' => 360, // 6 hours
                    ],
                ],
            ]);
    }

    public function test_trip_history_excludes_other_drivers_trips(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $otherUser = User::factory()->create();
        $otherDriver = Driver::factory()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => Vehicle::factory(),
        ]);

        $business = Business::factory()->create();

        // Create trip for our driver
        $this->createCompletedTripWithDetails($driver, $vehicle, $business, today(), 10.0, 1);

        // Create trip for other driver
        $this->createCompletedTripWithDetails($otherDriver, $otherDriver->vehicle, $business, today(), 20.0, 2);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/trips/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_trip_history_empty_for_new_driver(): void
    {
        $user = User::factory()->create();
        Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => Vehicle::factory(),
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/trips/history');

        $response->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_trip_history(): void
    {
        $response = $this->getJson('/api/v1/driver/trips/history');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createCompletedTripWithDetails(
        Driver $driver,
        Vehicle $vehicle,
        Business $business,
        Carbon $date,
        float $actualKm,
        int $destinationCount
    ): Trip {
        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
            'status' => DeliveryRequestStatus::Completed,
        ]);

        for ($i = 0; $i < $destinationCount; $i++) {
            Destination::factory()->create([
                'delivery_request_id' => $deliveryRequest->id,
                'status' => DestinationStatus::Completed,
                'completed_at' => $date,
            ]);
        }

        return Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::Completed,
            'actual_km' => $actualKm,
            'started_at' => $date->copy()->setHour(8),
            'completed_at' => $date->copy()->setHour(12),
            'created_at' => $date,
        ]);
    }
}
