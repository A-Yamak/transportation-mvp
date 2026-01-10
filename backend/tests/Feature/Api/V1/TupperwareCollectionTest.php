<?php

namespace Tests\Feature\Api\V1;

use App\Models\Destination;
use App\Models\Driver;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
// Using Passport auth via TestCase::actingAs($user, 'api')
use Tests\TestCase;

class TupperwareCollectionTest extends TestCase
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
     * Test driver can pickup tupperware from shop
     */
    public function test_driver_can_pickup_tupperware(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => 'boxes',
                        'quantity' => 5,
                    ],
                    [
                        'product_type' => 'trays',
                        'quantity' => 3,
                    ]
                ],
                'notes' => 'Containers in good condition'
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'shop_id',
                        'product_type',
                        'quantity_returned',
                        'shop_balance_after',
                        'movement_type_label',
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test getting shop tupperware balance
     */
    public function test_get_shop_tupperware_balance(): void
    {
        // Setup
        $shop = Shop::factory()->create();

        // Act
        $response = $this->getJson(
            "/api/v1/driver/shops/{$shop->id}/tupperware-balance"
        );

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data'
            ]);
    }

    /**
     * Test cannot pickup tupperware without items
     */
    public function test_cannot_pickup_empty_list(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [],
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('tupperware');
    }

    /**
     * Test invalid product type validation
     */
    public function test_invalid_product_type(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => '', // Empty!
                        'quantity' => 5,
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('tupperware.0.product_type');
    }

    /**
     * Test negative quantity rejected
     */
    public function test_negative_quantity_rejected(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => 'boxes',
                        'quantity' => -5, // Negative!
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('tupperware.0.quantity');
    }

    /**
     * Test zero quantity is allowed
     */
    public function test_zero_quantity_allowed(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => 'boxes',
                        'quantity' => 0,
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(201);
    }

    /**
     * Test optional notes field
     */
    public function test_optional_notes_field(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act - Without notes
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => 'boxes',
                        'quantity' => 5,
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(201);
    }

    /**
     * Test cannot pickup for wrong destination
     */
    public function test_cannot_pickup_for_wrong_destination(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        $other_trip = Trip::factory()->create();
        $other_destination = Destination::factory()
            ->forTrip($other_trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$other_destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => 'boxes',
                        'quantity' => 5,
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test product type with max length validation
     */
    public function test_product_type_max_length(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => str_repeat('a', 51), // Exceeds 50 chars
                        'quantity' => 5,
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors('tupperware.0.product_type');
    }

    /**
     * Test multiple product types in single call
     */
    public function test_multiple_product_types(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    ['product_type' => 'boxes', 'quantity' => 5],
                    ['product_type' => 'trays', 'quantity' => 3],
                    ['product_type' => 'bags', 'quantity' => 2],
                    ['product_type' => 'containers', 'quantity' => 1],
                ]
            ]
        );

        // Assert
        $response->assertStatus(201)
            ->assertJsonCount(4, 'data');
    }

    /**
     * Test unauthenticated request fails
     */
    public function test_unauthenticated_request_fails(): void
    {
        // Setup - test without authentication
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Clear authentication from setUp
        $this->app['auth']->forgetGuards();

        // Act - Without authentication
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    ['product_type' => 'boxes', 'quantity' => 5]
                ]
            ]
        );

        // Assert - Passport returns 401 for unauthenticated requests
        $response->assertStatus(401);
    }

    /**
     * Test large quantity values
     */
    public function test_large_quantity_values(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->for($this->driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->for($shop)
            ->create();

        // Act
        $response = $this->postJson(
            "/api/v1/driver/trips/{$trip->id}/destinations/{$destination->id}/collect-tupperware",
            [
                'tupperware' => [
                    [
                        'product_type' => 'boxes',
                        'quantity' => 99999, // Large number
                    ]
                ]
            ]
        );

        // Assert
        $response->assertStatus(201);
    }
}
