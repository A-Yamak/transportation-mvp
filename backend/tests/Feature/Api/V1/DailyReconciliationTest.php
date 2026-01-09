<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\ShortageReason;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\PaymentCollection;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = Driver::factory()
            ->has(User::factory())
            ->create();
        Sanctum::actingAs($this->driver->user);
    }

    /**
     * Test driver can end day
     */
    public function test_driver_can_end_day(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Record payment
        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'driver_id',
                    'reconciliation_date',
                    'total_expected',
                    'total_collected',
                    'total_cash',
                    'total_cliq',
                    'trips_completed',
                    'deliveries_completed',
                    'collection_rate',
                    'status',
                    'shop_breakdown',
                ]
            ]);

        // Verify data
        $response->assertJson([
            'data' => [
                'total_expected' => 1000.00,
                'total_collected' => 1000.00,
                'total_cash' => 1000.00,
                'trips_completed' => 1,
            ]
        ]);
    }

    /**
     * Test get day reconciliation
     */
    public function test_get_day_reconciliation(): void
    {
        // Setup: First end day
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 500.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 500.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        $this->postJson('/api/v1/driver/day/end');

        // Act
        $response = $this->getJson('/api/v1/driver/day/reconciliation');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'total_collected',
                ]
            ]);
    }

    /**
     * Test get day reconciliation when none exists
     */
    public function test_get_day_reconciliation_not_found(): void
    {
        // Act - Without creating any reconciliation
        $response = $this->getJson('/api/v1/driver/day/reconciliation');

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test submit reconciliation
     */
    public function test_driver_can_submit_reconciliation(): void
    {
        // Setup: Create and end day
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        $endDayResponse = $this->postJson('/api/v1/driver/day/end');
        $reconciliationId = $endDayResponse->json('data.id');

        // Act
        $response = $this->postJson('/api/v1/driver/reconciliation/submit', [
            'reconciliation_id' => $reconciliationId,
            'notes' => 'Daily reconciliation submitted'
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }

    /**
     * Test reconciliation with multiple trips
     */
    public function test_reconciliation_multiple_trips(): void
    {
        // Setup
        $trip1 = Trip::factory()->for($this->driver)->create();
        $trip2 = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()
            ->for($trip1)
            ->create(['amount_to_collect' => 500.00]);
        $dest2 = Destination::factory()
            ->for($trip2)
            ->create(['amount_to_collect' => 300.00]);

        // Collect payments
        $this->postJson(
            "/api/v1/driver/trips/{$trip1->id}/destinations/{$dest1->id}/collect-payment",
            [
                'amount_collected' => 500.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        $this->postJson(
            "/api/v1/driver/trips/{$trip2->id}/destinations/{$dest2->id}/collect-payment",
            [
                'amount_collected' => 300.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_collected' => 800.00,
                    'trips_completed' => 2,
                    'deliveries_completed' => 2,
                ]
            ]);
    }

    /**
     * Test reconciliation with cash and CliQ split
     */
    public function test_reconciliation_cash_cliq_split(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 600.00]);
        $dest2 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 400.00]);

        // Cash
        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest1->id}/collect-payment",
            [
                'amount_collected' => 600.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // CliQ
        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest2->id}/collect-payment",
            [
                'amount_collected' => 400.00,
                'payment_method' => PaymentMethod::CliqNow->value,
                'cliq_reference' => 'TXN-001',
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_cash' => 600.00,
                    'total_cliq' => 400.00,
                ]
            ]);
    }

    /**
     * Test reconciliation with shortages
     */
    public function test_reconciliation_with_shortages(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 500.00]);

        // Full payment
        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest1->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Partial
        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest2->id}/collect-payment",
            [
                'amount_collected' => 300.00,
                'payment_method' => PaymentMethod::Cash->value,
                'shortage_reason' => ShortageReason::CustomerAbsent->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_expected' => 1500.00,
                    'total_collected' => 1300.00,
                ]
            ]);
    }

    /**
     * Test collection rate calculation
     */
    public function test_reconciliation_collection_rate(): void
    {
        // Setup: 1250 of 1500 = 83.33%
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 500.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest1->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest2->id}/collect-payment",
            [
                'amount_collected' => 250.00,
                'payment_method' => PaymentMethod::Cash->value,
                'shortage_reason' => ShortageReason::InsufficientFunds->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $expectedRate = (1250 / 1500) * 100;
        $response->assertStatus(201)
            ->assertJsonPath('data.collection_rate', round($expectedRate, 2));
    }

    /**
     * Test shop breakdown in reconciliation
     */
    public function test_reconciliation_shop_breakdown(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();

        $dest1 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 500.00]);
        $dest2 = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 300.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest1->id}/collect-payment",
            [
                'amount_collected' => 500.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest2->id}/collect-payment",
            [
                'amount_collected' => 300.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.shop_breakdown', fn($breakdown) => is_array($breakdown) && count($breakdown) > 0);
    }

    /**
     * Test reconciliation with no collections
     */
    public function test_reconciliation_no_collections(): void
    {
        // Setup: Trip with no payments
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Don't collect any payment

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_collected' => 0.00,
                    'collection_rate' => 0.0,
                ]
            ]);
    }

    /**
     * Test optional notes on end day
     */
    public function test_end_day_optional_notes(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 100.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 100.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Act - With notes
        $response = $this->postJson('/api/v1/driver/day/end', [
            'notes' => 'All collections completed successfully'
        ]);

        // Assert
        $response->assertStatus(201);
    }

    /**
     * Test optional notes on submit
     */
    public function test_submit_with_optional_notes(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 100.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 100.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        $endDayResponse = $this->postJson('/api/v1/driver/day/end');
        $reconciliationId = $endDayResponse->json('data.id');

        // Act
        $response = $this->postJson('/api/v1/driver/reconciliation/submit', [
            'reconciliation_id' => $reconciliationId,
            'notes' => 'All collections verified and matched'
        ]);

        // Assert
        $response->assertStatus(200);
    }

    /**
     * Test submit requires reconciliation ID
     */
    public function test_submit_requires_reconciliation_id(): void
    {
        // Act
        $response = $this->postJson('/api/v1/driver/reconciliation/submit', [
            'notes' => 'Some notes'
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('reconciliation_id');
    }

    /**
     * Test cannot submit non-existent reconciliation
     */
    public function test_cannot_submit_nonexistent_reconciliation(): void
    {
        // Act
        $response = $this->postJson('/api/v1/driver/reconciliation/submit', [
            'reconciliation_id' => 'invalid-uuid-12345',
            'notes' => 'Some notes'
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test unauthenticated request fails
     */
    public function test_unauthenticated_request_fails(): void
    {
        // Setup
        Sanctum::useActualEncryption();

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(401);
    }

    /**
     * Test reconciliation date defaults to today
     */
    public function test_reconciliation_date_defaults_to_today(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 100.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 100.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.reconciliation_date', Carbon::today()->toDateString());
    }

    /**
     * Test reconciliation status is pending initially
     */
    public function test_reconciliation_status_pending(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $dest = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 100.00]);

        $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$dest->id}/collect-payment",
            [
                'amount_collected' => 100.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Act
        $response = $this->postJson('/api/v1/driver/day/end');

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }
}
