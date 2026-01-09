<?php

namespace Tests\Unit\Models;

use App\Models\Shop;
use App\Models\WasteCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopTest extends TestCase
{
    #[Test]
    public function get_navigation_url_generates_correct_google_maps_link()
    {
        $shop = Shop::factory()
            ->state([
                'lat' => 31.9539,
                'lng' => 35.9106,
            ])
            ->create();

        $url = $shop->getNavigationUrl();

        $this->assertStringContainsString('google.com/maps/dir', $url);
        $this->assertStringContainsString('31.9539', $url);
        $this->assertStringContainsString('35.9106', $url);
        $this->assertStringContainsString('driving', $url);
    }

    #[Test]
    public function get_total_waste_for_period_aggregates_waste_items()
    {
        $shop = Shop::factory()->create();

        // Create waste collections with items
        $collection1 = WasteCollection::factory()
            ->state([
                'shop_id' => $shop->id,
                'collection_date' => now()->subDays(5)->toDateString(),
            ])
            ->create();

        $collection1->items()->createMany([
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
                'pieces_waste' => 4,
                'delivered_at' => now()->toDateString(),
                'expires_at' => now()->toDateString(),
            ],
        ]);

        $from = now()->subDays(10);
        $to = now();

        // Total waste for period: 2 + 4 = 6
        $total = $shop->getTotalWasteForPeriod($from, $to);

        $this->assertEquals(6, $total);
    }

    #[Test]
    public function get_total_waste_for_period_respects_date_range()
    {
        $shop = Shop::factory()->create();

        // Create collection outside date range
        $oldCollection = WasteCollection::factory()
            ->state([
                'shop_id' => $shop->id,
                'collection_date' => now()->subDays(20)->toDateString(),
            ])
            ->create();

        $oldCollection->items()->create([
            'order_item_id' => 'ITEM-OLD',
            'product_name' => 'Old Waste',
            'quantity_delivered' => 10,
            'pieces_waste' => 10,
            'delivered_at' => now()->toDateString(),
            'expires_at' => now()->toDateString(),
        ]);

        // Create collection within date range
        $newCollection = WasteCollection::factory()
            ->state([
                'shop_id' => $shop->id,
                'collection_date' => now()->subDays(5)->toDateString(),
            ])
            ->create();

        $newCollection->items()->create([
            'order_item_id' => 'ITEM-NEW',
            'product_name' => 'New Waste',
            'quantity_delivered' => 20,
            'pieces_waste' => 5,
            'delivered_at' => now()->toDateString(),
            'expires_at' => now()->toDateString(),
        ]);

        $from = now()->subDays(10);
        $to = now();

        $total = $shop->getTotalWasteForPeriod($from, $to);

        // Should only count new collection (5), not old (10)
        $this->assertEquals(5, $total);
    }
}
