<?php

namespace Tests\Unit\Services;

use App\Models\Destination;
use App\Models\Shop;
use App\Models\TupperwareMovement;
use App\Models\Trip;
use App\Enums\MovementType;
use App\Services\TupperwareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TupperwareServiceTest extends TestCase
{
    use RefreshDatabase;

    private TupperwareService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TupperwareService::class);
    }

    /**
     * Test getting shop balance for a product type
     */
    public function test_get_shop_balance_zero_initial(): void
    {
        // Setup
        $shop = Shop::factory()->create();

        // Act
        $balance = $this->service->getShopBalance($shop, 'boxes');

        // Assert
        $this->assertEquals(0, $balance);
    }

    /**
     * Test shop balance after multiple deliveries
     */
    public function test_get_shop_balance_after_deliveries(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Act: Record 3 deliveries
        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 10,
            notes: 'First delivery'
        );

        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 5,
            notes: 'Second delivery'
        );

        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'trays',
            quantityDelivered: 8,
            notes: 'Tray delivery'
        );

        // Assert
        $this->assertEquals(15, $this->service->getShopBalance($shop, 'boxes'));
        $this->assertEquals(8, $this->service->getShopBalance($shop, 'trays'));
    }

    /**
     * Test shop balance after pickup (return)
     */
    public function test_get_shop_balance_after_pickups(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Record deliveries first
        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 20
        );

        // Act: Record pickups
        $this->service->recordPickup(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityReturned: 5,
            notes: 'First pickup'
        );

        $this->service->recordPickup(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityReturned: 3,
            notes: 'Second pickup'
        );

        // Assert
        $this->assertEquals(12, $this->service->getShopBalance($shop, 'boxes'));
    }

    /**
     * Test recording delivery creates movement with correct balance
     */
    public function test_record_delivery_creates_movement(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Act
        $movement = $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 10,
            notes: 'Test delivery'
        );

        // Assert
        $this->assertInstanceOf(TupperwareMovement::class, $movement);
        $this->assertEquals($shop->id, $movement->shop_id);
        $this->assertEquals('boxes', $movement->product_type);
        $this->assertEquals(10, $movement->quantity_delivered);
        $this->assertEquals(0, $movement->shop_balance_before);
        $this->assertEquals(10, $movement->shop_balance_after);
        $this->assertEquals(MovementType::Delivery, $movement->movement_type);
    }

    /**
     * Test recording pickup creates movement with correct balance
     */
    public function test_record_pickup_creates_movement(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // First deliver
        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 20
        );

        // Act: Pickup
        $movement = $this->service->recordPickup(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityReturned: 5,
            notes: 'Return pickup'
        );

        // Assert
        $this->assertInstanceOf(TupperwareMovement::class, $movement);
        $this->assertEquals(20, $movement->shop_balance_before);
        $this->assertEquals(15, $movement->shop_balance_after);
        $this->assertEquals(5, $movement->quantity_returned);
        $this->assertEquals(MovementType::Return, $movement->movement_type);
    }

    /**
     * Test cannot pickup more than available balance
     */
    public function test_cannot_pickup_more_than_balance(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Deliver 10 boxes
        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 10
        );

        // Act & Assert: Try to return 15 (more than available)
        $movement = $this->service->recordPickup(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityReturned: 15
        );

        // Should clamp to 0 minimum
        $this->assertEquals(0, $movement->shop_balance_after);
        $this->assertTrue($movement->shop_balance_after >= 0);
    }

    /**
     * Test recording adjustment
     */
    public function test_record_adjustment(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Initial delivery
        $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 20
        );

        // Act: Adjustment (e.g., lost 3 boxes)
        $movement = $this->service->recordAdjustment(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            adjustment: -3,
            notes: 'Lost in transport'
        );

        // Assert
        $this->assertEquals(17, $movement->shop_balance_after);
        $this->assertEquals(MovementType::Adjustment, $movement->movement_type);
    }

    /**
     * Test getting all balances for a shop
     */
    public function test_get_shop_all_balances(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Record deliveries of multiple types
        $this->service->recordDelivery($shop, $destination, 'boxes', 10);
        $this->service->recordDelivery($shop, $destination, 'trays', 15);
        $this->service->recordDelivery($shop, $destination, 'bags', 20);

        // Act
        $balances = $this->service->getShopAllBalances($shop);

        // Assert
        $this->assertArrayHasKey('boxes', $balances);
        $this->assertArrayHasKey('trays', $balances);
        $this->assertArrayHasKey('bags', $balances);
        $this->assertEquals(10, $balances['boxes']);
        $this->assertEquals(15, $balances['trays']);
        $this->assertEquals(20, $balances['bags']);
    }

    /**
     * Test movement history retrieval
     */
    public function test_get_movement_history(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Record multiple movements
        $this->service->recordDelivery($shop, $destination, 'boxes', 10);
        $this->service->recordPickup($shop, $destination, 'boxes', 3);
        $this->service->recordDelivery($shop, $destination, 'boxes', 5);

        // Act
        $history = $this->service->getMovementHistory($shop, 'boxes');

        // Assert
        $this->assertCount(3, $history);
        $this->assertEquals(MovementType::Delivery, $history[0]->movement_type);
        $this->assertEquals(MovementType::Return, $history[1]->movement_type);
        $this->assertEquals(MovementType::Delivery, $history[2]->movement_type);
    }

    /**
     * Test getting statistics for a shop
     */
    public function test_get_statistics(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Create various movements
        $this->service->recordDelivery($shop, $destination, 'boxes', 10);
        $this->service->recordDelivery($shop, $destination, 'boxes', 5);
        $this->service->recordPickup($shop, $destination, 'boxes', 3);

        // Act
        $stats = $this->service->getStatistics($shop, 'boxes');

        // Assert
        $this->assertEquals(15, $stats['total_delivered']);
        $this->assertEquals(3, $stats['total_returned']);
        $this->assertEquals(12, $stats['current_balance']);
        $this->assertEquals(3, $stats['movement_count']);
    }

    /**
     * Test identifying high balance shops
     */
    public function test_get_high_balance_shops(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        $shop1 = Shop::factory()->create(); // Will have 60 boxes
        $shop2 = Shop::factory()->create(); // Will have 30 boxes

        // Record deliveries
        $this->service->recordDelivery($shop1, $destination, 'boxes', 60);
        $this->service->recordDelivery($shop2, $destination, 'boxes', 30);

        // Act (threshold: 50)
        $highBalance = $this->service->getHighBalanceShops(threshold: 50);

        // Assert
        $this->assertTrue($highBalance->contains('id', $shop1->id));
        $this->assertFalse($highBalance->contains('id', $shop2->id));
    }

    /**
     * Test identifying low balance shops
     */
    public function test_get_low_balance_shops(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        $shop1 = Shop::factory()->create(); // Will have 3 boxes
        $shop2 = Shop::factory()->create(); // Will have 10 boxes

        // Record deliveries
        $this->service->recordDelivery($shop1, $destination, 'boxes', 3);
        $this->service->recordDelivery($shop2, $destination, 'boxes', 10);

        // Act (threshold: 5)
        $lowBalance = $this->service->getLowBalanceShops(threshold: 5);

        // Assert
        $this->assertTrue($lowBalance->contains('id', $shop1->id));
        $this->assertFalse($lowBalance->contains('id', $shop2->id));
    }

    /**
     * Test net change calculation for multiple product types
     */
    public function test_balance_calculation_with_multiple_product_types(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Act: Complex movements
        $this->service->recordDelivery($shop, $destination, 'boxes', 20);
        $this->service->recordPickup($shop, $destination, 'boxes', 5);
        $this->service->recordDelivery($shop, $destination, 'boxes', 10);

        $this->service->recordDelivery($shop, $destination, 'trays', 15);
        $this->service->recordPickup($shop, $destination, 'trays', 7);

        $this->service->recordDelivery($shop, $destination, 'bags', 8);

        // Assert
        $this->assertEquals(25, $this->service->getShopBalance($shop, 'boxes')); // 20 - 5 + 10
        $this->assertEquals(8, $this->service->getShopBalance($shop, 'trays'));  // 15 - 7
        $this->assertEquals(8, $this->service->getShopBalance($shop, 'bags'));   // 8
    }

    /**
     * Test that movements are linked to destination and trip
     */
    public function test_movement_relationships(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Act
        $movement = $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 10
        );

        // Assert
        $this->assertEquals($shop->id, $movement->shop_id);
        $this->assertEquals($destination->id, $movement->destination_id);
        $this->assertEquals($trip->id, $movement->trip_id);
        $this->assertNotNull($movement->driver_id);
    }

    /**
     * Test zero quantity operations
     */
    public function test_zero_quantity_operations(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Act: Record zero quantity (edge case)
        $movement = $this->service->recordDelivery(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityDelivered: 0
        );

        // Assert: Should still create movement for audit trail
        $this->assertEquals(0, $movement->quantity_delivered);
        $this->assertEquals(0, $this->service->getShopBalance($shop, 'boxes'));
    }

    /**
     * Test balance never goes negative
     */
    public function test_balance_never_negative(): void
    {
        // Setup
        $shop = Shop::factory()->create();
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->forTrip($trip)->create();

        // Act: Try extreme pickup
        $this->service->recordPickup(
            shop: $shop,
            destination: $destination,
            productType: 'boxes',
            quantityReturned: 1000
        );

        // Assert
        $balance = $this->service->getShopBalance($shop, 'boxes');
        $this->assertGreaterThanOrEqual(0, $balance);
    }
}
