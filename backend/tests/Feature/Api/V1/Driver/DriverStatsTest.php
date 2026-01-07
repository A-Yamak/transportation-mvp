<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Driver;

use App\Enums\DeliveryRequestStatus;
use App\Enums\DestinationStatus;
use App\Enums\TripStatus;
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
 * Driver Stats API Tests
 *
 * Tests for driver statistics endpoint:
 * - GET /api/v1/driver/stats - Get KM and delivery stats
 */
class DriverStatsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // GET /api/v1/driver/stats
    // =========================================================================

    public function test_driver_can_get_their_stats(): void
    {
        $vehicle = Vehicle::factory()->create([
            'acquisition_km' => 45000,
            'total_km_driven' => 52500,
            'monthly_km_app' => 1200,
        ]);

        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Create completed trips for today
        $this->createCompletedTrip($driver, $vehicle, today(), 25.5);
        $this->createCompletedTrip($driver, $vehicle, today(), 18.3);

        // Create completed trips for this month (but not today)
        $this->createCompletedTrip($driver, $vehicle, today()->subDays(5), 45.0);

        // Create a completed trip from last month
        $this->createCompletedTrip($driver, $vehicle, today()->subMonth(), 100.0);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'today' => [
                        'trips_count',
                        'destinations_completed',
                        'km_driven',
                    ],
                    'this_month' => [
                        'trips_count',
                        'destinations_completed',
                        'km_driven',
                    ],
                    'all_time' => [
                        'trips_count',
                        'destinations_completed',
                        'km_driven',
                    ],
                    'vehicle' => [
                        'acquisition_km',
                        'total_km_driven',
                        'app_tracked_km',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'today' => [
                        'trips_count' => 2,
                        'km_driven' => 43.8, // 25.5 + 18.3
                    ],
                    'this_month' => [
                        'trips_count' => 3, // 2 today + 1 five days ago
                        'km_driven' => 88.8, // 25.5 + 18.3 + 45.0
                    ],
                    'all_time' => [
                        'trips_count' => 4,
                        'km_driven' => 188.8, // all trips
                    ],
                    'vehicle' => [
                        'acquisition_km' => 45000,
                        'total_km_driven' => 52500,
                        'app_tracked_km' => 7500,
                    ],
                ],
            ]);
    }

    public function test_driver_stats_includes_destination_counts(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Create trip with 3 completed destinations today
        $trip = $this->createCompletedTrip($driver, $vehicle, today(), 30.0, 3);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/stats');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'today' => [
                        'destinations_completed' => 3,
                    ],
                ],
            ]);
    }

    public function test_driver_with_no_trips_returns_zero_stats(): void
    {
        $vehicle = Vehicle::factory()->create([
            'acquisition_km' => 45000,
            'total_km_driven' => 45000,
            'monthly_km_app' => 0,
        ]);

        $user = User::factory()->create();
        Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/stats');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'today' => [
                        'trips_count' => 0,
                        'destinations_completed' => 0,
                        'km_driven' => 0,
                    ],
                    'this_month' => [
                        'trips_count' => 0,
                        'destinations_completed' => 0,
                        'km_driven' => 0,
                    ],
                    'all_time' => [
                        'trips_count' => 0,
                        'destinations_completed' => 0,
                        'km_driven' => 0,
                    ],
                ],
            ]);
    }

    public function test_driver_without_vehicle_still_gets_trip_stats(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => null,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/stats');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'vehicle' => null,
                ],
            ]);
    }

    public function test_stats_only_count_completed_trips(): void
    {
        $vehicle = Vehicle::factory()->create();
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Create one completed trip
        $this->createCompletedTrip($driver, $vehicle, today(), 20.0);

        // Create one in-progress trip (should not count)
        $this->createInProgressTrip($driver, $vehicle, today());

        // Create one cancelled trip (should not count)
        $this->createCancelledTrip($driver, $vehicle, today());

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/stats');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'today' => [
                        'trips_count' => 1,
                        'km_driven' => 20.0,
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_stats(): void
    {
        $response = $this->getJson('/api/v1/driver/stats');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function createCompletedTrip(
        Driver $driver,
        Vehicle $vehicle,
        Carbon $date,
        float $actualKm,
        int $destinationCount = 1
    ): Trip {
        $deliveryRequest = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::Completed,
        ]);

        // Create destinations
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

    private function createInProgressTrip(Driver $driver, Vehicle $vehicle, Carbon $date): Trip
    {
        $deliveryRequest = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::InProgress,
        ]);

        return Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::InProgress,
            'actual_km' => null,
            'started_at' => $date->copy()->setHour(8),
            'completed_at' => null,
            'created_at' => $date,
        ]);
    }

    private function createCancelledTrip(Driver $driver, Vehicle $vehicle, Carbon $date): Trip
    {
        $deliveryRequest = DeliveryRequest::factory()->create([
            'status' => DeliveryRequestStatus::Cancelled,
        ]);

        return Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::Cancelled,
            'actual_km' => null,
            'started_at' => null,
            'completed_at' => null,
            'created_at' => $date,
        ]);
    }
}
