<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShortageReason;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Authenticate as a driver
        $this->driver = Driver::factory()
            ->has(User::factory())
            ->create();
        Sanctum::actingAs($this->driver->user);
    }

    /**
     * Test driver can collect full payment in cash
     */
    public function test_driver_can_collect_full_payment(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
                'notes' => 'Full payment received'
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'destination_id',
                    'trip_id',
                    'amount_expected',
                    'amount_collected',
                    'shortage_amount',
                    'payment_method',
                    'payment_status',
                    'payment_method_label',
                    'payment_status_label',
                ]
            ])
            ->assertJson([
                'data' => [
                    'amount_collected' => 1000.00,
                    'shortage_amount' => 0.00,
                    'payment_method' => PaymentMethod::Cash->value,
                    'payment_status' => PaymentStatus::Collected->value,
                ]
            ]);

        // Verify destination updated
        $destination->refresh();
        $this->assertEquals(1000.00, $destination->amount_collected);
        $this->assertEquals(PaymentMethod::Cash, $destination->payment_method);
        $this->assertEquals(PaymentStatus::Collected, $destination->payment_status);
    }

    /**
     * Test driver can collect partial payment with reason
     */
    public function test_driver_can_collect_partial_payment(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 750.00,
                'payment_method' => PaymentMethod::Cash->value,
                'shortage_reason' => ShortageReason::CustomerAbsent->value,
                'notes' => 'Customer not home'
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'amount_collected' => 750.00,
                    'shortage_amount' => 250.00,
                    'payment_status' => PaymentStatus::Partial->value,
                ]
            ]);

        $destination->refresh();
        $this->assertEquals(ShortageReason::CustomerAbsent->value, $destination->payment_reference);
    }

    /**
     * Test payment shortage requires reason
     */
    public function test_payment_shortage_requires_reason(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act - Try to collect partial without reason
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 750.00,
                'payment_method' => PaymentMethod::Cash->value,
                // shortage_reason missing!
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('shortage_reason');
    }

    /**
     * Test CliQ payment requires reference number
     */
    public function test_cliq_payment_requires_reference(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act - Try CliQ without reference
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::CliqNow->value,
                // cliq_reference missing!
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('cliq_reference');
    }

    /**
     * Test driver cannot collect more than expected
     */
    public function test_cannot_collect_more_than_expected(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act - Try to collect more
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1500.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Assert - Should fail validation
        $response->assertStatus(422);
    }

    /**
     * Test collecting via CliQ Now with reference
     */
    public function test_collect_cliq_now_payment(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::CliqNow->value,
                'cliq_reference' => 'CLIQ-TXN-123456',
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'payment_method' => PaymentMethod::CliqNow->value,
                ]
            ]);

        $destination->refresh();
        $this->assertEquals(PaymentMethod::CliqNow, $destination->payment_method);
    }

    /**
     * Test collecting via CliQ Later
     */
    public function test_collect_cliq_later_payment(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::CliqLater->value,
                'cliq_reference' => 'CLIQ-LATER-789',
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'payment_method' => PaymentMethod::CliqLater->value,
                ]
            ]);
    }

    /**
     * Test zero payment collection
     */
    public function test_collect_zero_payment(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 0.00,
                'payment_method' => PaymentMethod::Cash->value,
                'shortage_reason' => ShortageReason::CustomerRefused->value,
                'notes' => 'Customer refused'
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'amount_collected' => 0.00,
                    'shortage_amount' => 1000.00,
                    'payment_status' => PaymentStatus::Failed->value,
                ]
            ]);
    }

    /**
     * Test cannot collect payment for wrong destination
     */
    public function test_cannot_collect_for_wrong_destination(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        $other_trip = Trip::factory()->create(); // Different driver's trip
        $other_destination = Destination::factory()
            ->for($other_trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act - Try to collect for destination in wrong trip
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$other_destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test payment collected_at timestamp is recorded
     */
    public function test_payment_collected_at_recorded(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.payment_collected_at', fn($date) => !empty($date));

        $destination->refresh();
        $this->assertNotNull($destination->payment_collected_at);
    }

    /**
     * Test decimal precision in payment amounts
     */
    public function test_decimal_precision_amounts(): void
    {
        // Setup
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1234.56]);

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 987.33,
                'payment_method' => PaymentMethod::Cash->value,
                'shortage_reason' => ShortageReason::Other->value,
            ]
        );

        // Assert - Verify precision
        $response->assertStatus(201)
            ->assertJsonPath('data.amount_collected', 987.33)
            ->assertJsonPath('data.shortage_amount', 247.23);
    }

    /**
     * Test different shortage reasons
     */
    public function test_different_shortage_reasons(): void
    {
        $trip = Trip::factory()->for($this->driver)->create();

        $reasons = [
            ShortageReason::CustomerAbsent,
            ShortageReason::InsufficientFunds,
            ShortageReason::CustomerRefused,
            ShortageReason::PartialDelivery,
        ];

        foreach ($reasons as $reason) {
            // Setup
            $destination = Destination::factory()
                ->for($trip)
                ->create(['amount_to_collect' => 1000.00]);

            // Act
            $response = $this->postJson(
                "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
                [
                    'amount_collected' => 500.00,
                    'payment_method' => PaymentMethod::Cash->value,
                    'shortage_reason' => $reason->value,
                ]
            );

            // Assert
            $response->assertStatus(201);
        }
    }

    /**
     * Test unauthenticated request fails
     */
    public function test_unauthenticated_request_fails(): void
    {
        // Setup
        Sanctum::useActualEncryption();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->for($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act - Without authentication
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-payment",
            [
                'amount_collected' => 1000.00,
                'payment_method' => PaymentMethod::Cash->value,
            ]
        );

        // Assert
        $response->assertStatus(401);
    }
}
