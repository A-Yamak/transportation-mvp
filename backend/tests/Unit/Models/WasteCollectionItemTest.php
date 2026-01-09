<?php

namespace Tests\Unit\Models;

use App\Models\WasteCollectionItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WasteCollectionItemTest extends TestCase
{
    #[Test]
    public function pieces_sold_calculation_delivered_10_waste_3()
    {
        $item = WasteCollectionItem::factory()
            ->state([
                'quantity_delivered' => 10,
                'pieces_waste' => 3,
            ])
            ->create();

        $this->assertEquals(7, $item->pieces_sold);
    }

    #[Test]
    public function pieces_sold_handles_zero_waste()
    {
        $item = WasteCollectionItem::factory()
            ->noWaste()
            ->state(['quantity_delivered' => 20])
            ->create();

        $this->assertEquals(20, $item->pieces_sold);
    }

    #[Test]
    public function pieces_sold_handles_full_waste()
    {
        $item = WasteCollectionItem::factory()
            ->fullWaste()
            ->state(['quantity_delivered' => 15])
            ->create();

        $this->assertEquals(0, $item->pieces_sold);
    }

    #[Test]
    public function is_expired_returns_true_when_past_expiry()
    {
        $item = WasteCollectionItem::factory()
            ->expired()
            ->create();

        $this->assertTrue($item->isExpired());
    }

    #[Test]
    public function is_expired_returns_false_when_future_expiry()
    {
        $item = WasteCollectionItem::factory()
            ->notExpired()
            ->create();

        $this->assertFalse($item->isExpired());
    }

    #[Test]
    public function is_expired_returns_false_when_no_expiry_date()
    {
        $item = WasteCollectionItem::factory()
            ->state(['expires_at' => null])
            ->create();

        $this->assertFalse($item->isExpired());
    }

    #[Test]
    public function get_days_expired_calculates_correctly()
    {
        $item = WasteCollectionItem::factory()
            ->state(['expires_at' => now()->subDays(5)->toDateString()])
            ->create();

        $this->assertEquals(5, $item->getDaysExpired());
    }

    #[Test]
    public function get_days_expired_returns_zero_if_not_expired()
    {
        $item = WasteCollectionItem::factory()
            ->state(['expires_at' => now()->addDays(10)->toDateString()])
            ->create();

        $this->assertEquals(0, $item->getDaysExpired());
    }

    #[Test]
    public function get_waste_percentage_calculates_correctly()
    {
        $item = WasteCollectionItem::factory()
            ->state([
                'quantity_delivered' => 10,
                'pieces_waste' => 3,
            ])
            ->create();

        $this->assertEquals(30.0, $item->getWastePercentage());
    }

    #[Test]
    public function get_waste_percentage_handles_full_waste()
    {
        $item = WasteCollectionItem::factory()
            ->fullWaste()
            ->state(['quantity_delivered' => 20])
            ->create();

        $this->assertEquals(100.0, $item->getWastePercentage());
    }

    #[Test]
    public function get_waste_percentage_returns_zero_when_no_waste()
    {
        $item = WasteCollectionItem::factory()
            ->noWaste()
            ->state(['quantity_delivered' => 20])
            ->create();

        $this->assertEquals(0.0, $item->getWastePercentage());
    }

    #[Test]
    public function get_waste_percentage_handles_zero_quantity_delivered()
    {
        $item = WasteCollectionItem::factory()
            ->state([
                'quantity_delivered' => 0,
                'pieces_waste' => 0,
            ])
            ->create();

        $this->assertEquals(0.0, $item->getWastePercentage());
    }

    #[Test]
    public function is_valid_waste_quantity_allows_waste_less_than_delivered()
    {
        $item = WasteCollectionItem::factory()
            ->state([
                'quantity_delivered' => 20,
                'pieces_waste' => 10,
            ])
            ->create();

        $this->assertTrue($item->isValidWasteQuantity());
    }

    #[Test]
    public function is_valid_waste_quantity_allows_waste_equal_to_delivered()
    {
        $item = WasteCollectionItem::factory()
            ->state([
                'quantity_delivered' => 20,
                'pieces_waste' => 20,
            ])
            ->create();

        $this->assertTrue($item->isValidWasteQuantity());
    }

    #[Test]
    public function is_valid_waste_quantity_rejects_waste_exceeding_delivered()
    {
        $item = WasteCollectionItem::factory()
            ->state([
                'quantity_delivered' => 20,
                'pieces_waste' => 25,
            ])
            ->create();

        $this->assertFalse($item->isValidWasteQuantity());
    }
}
