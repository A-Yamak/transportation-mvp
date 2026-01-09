<?php

namespace Tests\Unit\Models;

use App\Models\WasteCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WasteCollectionTest extends TestCase
{
    #[Test]
    public function is_collected_returns_false_when_not_collected()
    {
        $collection = WasteCollection::factory()
            ->state(['collected_at' => null])
            ->create();

        $this->assertFalse($collection->isCollected());
    }

    #[Test]
    public function is_collected_returns_true_when_collected()
    {
        $collection = WasteCollection::factory()
            ->collected()
            ->create();

        $this->assertTrue($collection->isCollected());
    }

    #[Test]
    public function get_total_waste_pieces_aggregates_correctly()
    {
        $collection = WasteCollection::factory()->create();

        $collection->items()->createMany([
            [
                'order_item_id' => 'ITEM-001',
                'product_name' => 'Baklava',
                'quantity_delivered' => 10,
                'pieces_waste' => 3,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
            [
                'order_item_id' => 'ITEM-002',
                'product_name' => 'Kunafa',
                'quantity_delivered' => 20,
                'pieces_waste' => 5,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
        ]);

        // Total waste: 3 + 5 = 8
        $this->assertEquals(8, $collection->getTotalWastePieces());
    }

    #[Test]
    public function get_total_sold_pieces_calculates_correctly()
    {
        $collection = WasteCollection::factory()->create();

        $collection->items()->createMany([
            [
                'order_item_id' => 'ITEM-001',
                'product_name' => 'Baklava',
                'quantity_delivered' => 10,
                'pieces_waste' => 2,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
            [
                'order_item_id' => 'ITEM-002',
                'product_name' => 'Kunafa',
                'quantity_delivered' => 15,
                'pieces_waste' => 3,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
        ]);

        // Total sold: (10-2) + (15-3) = 8 + 12 = 20
        $this->assertEquals(20, $collection->getTotalSoldPieces());
    }

    #[Test]
    public function get_total_delivered_pieces_sums_correctly()
    {
        $collection = WasteCollection::factory()->create();

        $collection->items()->createMany([
            [
                'order_item_id' => 'ITEM-001',
                'product_name' => 'Baklava',
                'quantity_delivered' => 10,
                'pieces_waste' => 0,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
            [
                'order_item_id' => 'ITEM-002',
                'product_name' => 'Kunafa',
                'quantity_delivered' => 20,
                'pieces_waste' => 0,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
        ]);

        // Total delivered: 10 + 20 = 30
        $this->assertEquals(30, $collection->getTotalDeliveredPieces());
    }
}
