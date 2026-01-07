<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\DestinationItem;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Tests for price tracking in delivery requests.
 *
 * Tests cover:
 * - Creating delivery requests with items and prices
 * - Amount to collect per destination
 * - Item unit prices and line totals
 * - Driver viewing price information
 */
class DeliveryRequestPriceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private Driver $driver;
    private User $driverUser;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create business with API key
        $this->business = Business::factory()->create([
            'api_key' => 'test_api_key_123',
        ]);

        // Create driver with user
        $this->driverUser = User::factory()->create();
        $this->driver = Driver::factory()->create([
            'user_id' => $this->driverUser->id,
        ]);

        // Create vehicle
        $this->vehicle = Vehicle::factory()->create();
    }

    /** @test */
    public function it_can_create_delivery_request_with_amount_to_collect(): void
    {
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ORDER-001',
                    'address' => '123 Main St',
                    'lat' => 31.9539,
                    'lng' => 35.8753,
                    'amount_to_collect' => 150.50,
                ],
            ],
        ], [
            'X-API-Key' => 'test_api_key_123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.destinations.0.amount_to_collect', 150.50);

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'ORDER-001',
            'amount_to_collect' => 150.50,
        ]);
    }

    /** @test */
    public function it_can_create_delivery_request_with_items_and_unit_prices(): void
    {
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ORDER-002',
                    'address' => '456 Oak Ave',
                    'lat' => 31.9875,
                    'lng' => 35.8656,
                    'amount_to_collect' => 45.00,
                    'items' => [
                        [
                            'order_item_id' => 'ITEM-001',
                            'name' => 'Baklava Box',
                            'unit_price' => 15.00,
                            'quantity_ordered' => 2,
                        ],
                        [
                            'order_item_id' => 'ITEM-002',
                            'name' => 'Kunafa Tray',
                            'unit_price' => 7.50,
                            'quantity_ordered' => 2,
                        ],
                    ],
                ],
            ],
        ], [
            'X-API-Key' => 'test_api_key_123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.destinations.0.amount_to_collect', 45.00);
        $response->assertJsonPath('data.destinations.0.has_item_tracking', true);

        // Check items were created with prices
        $this->assertDatabaseHas('destination_items', [
            'order_item_id' => 'ITEM-001',
            'name' => 'Baklava Box',
            'unit_price' => 15.00,
            'quantity_ordered' => 2,
        ]);

        $this->assertDatabaseHas('destination_items', [
            'order_item_id' => 'ITEM-002',
            'name' => 'Kunafa Tray',
            'unit_price' => 7.50,
            'quantity_ordered' => 2,
        ]);
    }

    /** @test */
    public function it_calculates_line_total_for_items(): void
    {
        // Create a destination with items
        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $destination = Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'amount_to_collect' => 100.00,
        ]);

        $item = DestinationItem::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 25.00,
            'quantity_ordered' => 4,
            'quantity_delivered' => 0,
        ]);

        // Test model calculations
        $this->assertEquals(100.00, $item->line_total);
        $this->assertEquals(0.00, $item->delivered_total);

        // Update delivered quantity
        $item->update(['quantity_delivered' => 3]);
        $item->refresh();

        $this->assertEquals(75.00, $item->delivered_total);
        $this->assertEquals(1, $item->shortage);
    }

    /** @test */
    public function it_calculates_destination_total_from_items(): void
    {
        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $destination = Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        DestinationItem::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 10.00,
            'quantity_ordered' => 5,
        ]);

        DestinationItem::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 20.00,
            'quantity_ordered' => 2,
        ]);

        $destination->load('items');

        // 10*5 + 20*2 = 50 + 40 = 90
        $this->assertEquals(90.00, $destination->calculateTotalFromItems());
    }

    /** @test */
    public function driver_can_see_items_with_prices_in_trip_details(): void
    {
        Passport::actingAs($this->driverUser);

        // Create delivery request with items
        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $destination = Destination::factory()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'amount_to_collect' => 75.00,
        ]);

        DestinationItem::factory()->create([
            'destination_id' => $destination->id,
            'name' => 'Sweet Box',
            'unit_price' => 25.00,
            'quantity_ordered' => 3,
        ]);

        // Assign trip to driver
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        $response = $this->getJson("/api/v1/driver/trips/{$trip->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.destinations.0.amount_to_collect', 75.00);
        $response->assertJsonPath('data.destinations.0.items.0.name', 'Sweet Box');
        $response->assertJsonPath('data.destinations.0.items.0.unit_price', 25.00);
        $response->assertJsonPath('data.destinations.0.items.0.quantity_ordered', 3);
        $response->assertJsonPath('data.destinations.0.items.0.line_total', 75.00);
    }

    /** @test */
    public function it_validates_price_fields_correctly(): void
    {
        // Test negative amount_to_collect
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ORDER-003',
                    'address' => '789 Pine St',
                    'lat' => 31.95,
                    'lng' => 35.87,
                    'amount_to_collect' => -50.00,
                ],
            ],
        ], [
            'X-API-Key' => 'test_api_key_123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['destinations.0.amount_to_collect']);
    }

    /** @test */
    public function it_validates_item_unit_price_correctly(): void
    {
        // Test negative unit_price
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ORDER-004',
                    'address' => '101 Elm St',
                    'lat' => 31.95,
                    'lng' => 35.87,
                    'items' => [
                        [
                            'order_item_id' => 'ITEM-X',
                            'name' => 'Bad Item',
                            'unit_price' => -10.00,
                            'quantity_ordered' => 1,
                        ],
                    ],
                ],
            ],
        ], [
            'X-API-Key' => 'test_api_key_123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['destinations.0.items.0.unit_price']);
    }

    /** @test */
    public function it_stores_contact_info_with_destination(): void
    {
        $response = $this->postJson('/api/v1/delivery-requests', [
            'destinations' => [
                [
                    'external_id' => 'ORDER-005',
                    'address' => '200 Cedar Ave',
                    'lat' => 31.96,
                    'lng' => 35.88,
                    'contact_name' => 'Ahmad Shop',
                    'contact_phone' => '+962791234567',
                    'amount_to_collect' => 200.00,
                ],
            ],
        ], [
            'X-API-Key' => 'test_api_key_123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.destinations.0.contact_name', 'Ahmad Shop');
        $response->assertJsonPath('data.destinations.0.contact_phone', '+962791234567');

        $this->assertDatabaseHas('destinations', [
            'external_id' => 'ORDER-005',
            'contact_name' => 'Ahmad Shop',
            'contact_phone' => '+962791234567',
        ]);
    }
}
