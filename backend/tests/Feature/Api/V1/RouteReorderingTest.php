<?php

namespace Tests\Feature\Api\V1;

use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
// Using Passport auth via TestCase::actingAs($user, 'api')
use Tests\TestCase;

class RouteReorderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = Driver::factory()
            ->has(User::factory())
            ->create();
        $this->actingAs($this->driver->user, 'api');
    }

    /**
     * Test driver can reorder destinations
     */
    public function test_driver_can_reorder_destinations(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create(['sequence_order' => 0]);
        $dest2 = Destination::factory()->forTrip($trip)->create(['sequence_order' => 1]);
        $dest3 = Destination::factory()->forTrip($trip)->create(['sequence_order' => 2]);

        // Act: Reorder to [dest3, dest1, dest2]
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [
                    $dest3->id,
                    $dest1->id,
                    $dest2->id,
                ]
            ]
        );

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'sequence_order',
                        'address',
                    ]
                ]
            ]);

        // Verify sequence order updated
        $dest1->refresh();
        $dest2->refresh();
        $dest3->refresh();

        $this->assertEquals(1, $dest1->sequence_order);
        $this->assertEquals(2, $dest2->sequence_order);
        $this->assertEquals(0, $dest3->sequence_order);
    }

    /**
     * Test cannot reorder with missing destination ID
     */
    public function test_cannot_reorder_missing_destination_id(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();
        $dest3 = Destination::factory()->forTrip($trip)->create();

        // Act: Try to reorder with missing IDs
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [
                    $dest1->id,
                    $dest2->id,
                    // $dest3->id missing!
                ]
            ]
        );

        // Assert
        $response->assertStatus(422);
    }

    /**
     * Test cannot reorder after trip started
     */
    public function test_cannot_reorder_after_trip_started(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create(['started_at' => now()]);

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest2->id, $dest1->id]
            ]
        );

        // Assert
        $response->assertStatus(422);
    }

    /**
     * Test cannot reorder after trip completed
     */
    public function test_cannot_reorder_after_trip_completed(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create([
            'started_at' => now()->subHours(2),
            'completed_at' => now()
        ]);

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest2->id, $dest1->id]
            ]
        );

        // Assert
        $response->assertStatus(422);
    }

    /**
     * Test must provide at least 2 destinations
     */
    public function test_must_provide_minimum_destinations(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $dest1 = Destination::factory()->forTrip($trip)->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest1->id]
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('destination_ids');
    }

    /**
     * Test cannot reorder with invalid UUID
     */
    public function test_invalid_destination_uuid(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [
                    'not-a-uuid',
                    'also-not-a-uuid',
                ]
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_ids.0', 'destination_ids.1']);
    }

    /**
     * Test cannot reorder with non-existent destination
     */
    public function test_cannot_reorder_nonexistent_destination(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $fakeId = 'f47ac10b-58cc-4372-a567-0e02b2c3d479'; // Valid UUID, wrong destination

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest1->id, $fakeId]
            ]
        );

        // Assert
        $response->assertStatus(422);
    }

    /**
     * Test cannot reorder destination from another trip
     */
    public function test_cannot_reorder_destination_from_another_trip(): void
    {
        // Setup
        $trip1 = Trip::factory()->for($this->driver)->create();
        $trip2 = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()->forTrip($trip1)->create();
        $dest2 = Destination::factory()->forTrip($trip2)->create();

        // Act: Try to reorder trip1 with destination from trip2
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip1->id}/reorder-destinations",
            [
                'destination_ids' => [$dest2->id, $dest1->id]
            ]
        );

        // Assert
        $response->assertStatus(422);
    }

    /**
     * Test reordering many destinations
     */
    public function test_reorder_many_destinations(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $destinations = Destination::factory(10)->forTrip($trip)->create();
        $destinationIds = $destinations->pluck('id')->shuffle()->toArray();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => $destinationIds
            ]
        );

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');

        // Verify all have correct sequence
        foreach ($destinationIds as $index => $destinationId) {
            $this->assertDatabaseHas('destinations', [
                'id' => $destinationId,
                'sequence_order' => $index
            ]);
        }
    }

    /**
     * Test destination response includes sequence order
     */
    public function test_response_includes_sequence_order(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();
        $dest3 = Destination::factory()->forTrip($trip)->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest3->id, $dest1->id, $dest2->id]
            ]
        );

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.0.sequence_order', 0)
            ->assertJsonPath('data.1.sequence_order', 1)
            ->assertJsonPath('data.2.sequence_order', 2);
    }

    /**
     * Test reverse order reordering
     */
    public function test_reverse_order_reordering(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $destinations = Destination::factory(5)->forTrip($trip)->create([
            'sequence_order' => 0 // Will be updated
        ]);

        $reversedIds = $destinations->pluck('id')->reverse()->toArray();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => $reversedIds
            ]
        );

        // Assert
        $response->assertStatus(200);

        // Verify reverse order
        foreach ($reversedIds as $index => $id) {
            $this->assertDatabaseHas('destinations', [
                'id' => $id,
                'sequence_order' => $index
            ]);
        }
    }

    /**
     * Test unauthenticated request fails
     */
    public function test_unauthenticated_request_fails(): void
    {
        // Clear authentication from setUp
        $this->app['auth']->forgetGuards();

        // Setup - test without authentication
        $trip = Trip::factory()->create();

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();

        // Act - make request without authentication
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest2->id, $dest1->id]
            ]
        );

        // Assert
        $response->assertStatus(401);
    }

    /**
     * Test cannot reorder trip belonging to different driver
     */
    public function test_cannot_reorder_other_drivers_trip(): void
    {
        // Setup
        $otherDriver = Driver::factory()
            ->has(User::factory())
            ->create();

        $trip = Trip::factory()->for($otherDriver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest2->id, $dest1->id]
            ]
        );

        // Assert - Should fail because current driver doesn't own this trip
        $response->assertStatus(403);
    }

    /**
     * Test duplicate destination IDs in request
     */
    public function test_duplicate_destination_ids(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create();
        $dest2 = Destination::factory()->forTrip($trip)->create();

        // Act - Same destination twice
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/reorder-destinations",
            [
                'destination_ids' => [$dest1->id, $dest1->id, $dest2->id]
            ]
        );

        // Assert
        $response->assertStatus(422);
    }
}
