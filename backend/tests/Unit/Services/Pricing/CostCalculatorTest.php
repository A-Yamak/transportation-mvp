<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pricing;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\PricingTier;
use App\Services\Pricing\CostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected CostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(CostCalculator::class);
    }

    public function test_applies_distance_discount_correctly(): void
    {
        config(['pricing.distance_discounts' => [
            50 => 0.05,  // 5% discount over 50 KM
            100 => 0.10, // 10% discount over 100 KM
        ]]);

        $business = Business::factory()->create();
        $pricingTier = PricingTier::factory()->create([
            'business_type' => $business->business_type,
            'price_per_km' => 1.00,
            'base_fee' => 0.00,
            'minimum_cost' => 0.00,
            'effective_date' => now()->subDays(10),
        ]);

        // Test no discount (< 50 KM)
        $breakdown = $this->calculator->getCostBreakdown(40.00, $business);
        $this->assertEquals(0, $breakdown['discount_percent']);
        $this->assertEquals(40.00, $breakdown['total']);

        // Test 5% discount (50-99 KM)
        $breakdown = $this->calculator->getCostBreakdown(60.00, $business);
        $this->assertEquals(5, $breakdown['discount_percent']);
        $this->assertEquals(57.00, $breakdown['total']); // 60 - 5% = 57

        // Test 10% discount (100+ KM)
        $breakdown = $this->calculator->getCostBreakdown(120.00, $business);
        $this->assertEquals(10, $breakdown['discount_percent']);
        $this->assertEquals(108.00, $breakdown['total']); // 120 - 10% = 108
    }

    public function test_applies_tax_when_configured(): void
    {
        config(['pricing.tax_rate' => 0.16]); // 16% tax
        config(['pricing.distance_discounts' => []]); // Disable discounts for this test

        $business = Business::factory()->create();
        $pricingTier = PricingTier::factory()->create([
            'business_type' => $business->business_type,
            'price_per_km' => 1.00,
            'base_fee' => 0.00,
            'minimum_cost' => 0.00,
            'effective_date' => now()->subDays(10),
        ]);

        $breakdown = $this->calculator->getCostBreakdown(100.00, $business);

        $this->assertEquals(100.00, $breakdown['before_tax']);
        $this->assertEquals(16, $breakdown['tax_rate']);
        $this->assertEquals(16.00, $breakdown['tax_amount']);
        $this->assertEquals(116.00, $breakdown['total']);
    }

    public function test_returns_null_when_no_pricing_tier_exists(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        // Don't create any pricing tiers
        $tier = $this->calculator->getApplicableTier($business);

        $this->assertNull($tier);
    }

    public function test_uses_most_recent_effective_tier(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        // Create tiers with different effective dates
        PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.30,
            'effective_date' => now()->subDays(90),
        ]);

        $latestTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.50,
            'effective_date' => now()->subDays(30),
        ]);

        PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.70,
            'effective_date' => now()->subDays(60),
        ]);

        $tier = $this->calculator->getApplicableTier($business);

        $this->assertEquals($latestTier->id, $tier->id);
        $this->assertEquals(0.50, $tier->price_per_km);
    }

    public function test_minimum_cost_overrides_calculated_cost(): void
    {
        $business = Business::factory()->create();
        $pricingTier = PricingTier::factory()->create([
            'business_type' => $business->business_type,
            'price_per_km' => 0.10,
            'base_fee' => 0.00,
            'minimum_cost' => 20.00,
            'effective_date' => now()->subDays(10),
        ]);

        $breakdown = $this->calculator->getCostBreakdown(10.00, $business);

        // 10 km × 0.10 = 1.00, but minimum is 20.00
        $this->assertEquals(1.00, $breakdown['distance_cost']);
        $this->assertEquals(1.00, $breakdown['after_discount']);
        $this->assertTrue($breakdown['minimum_applied']);
        $this->assertEquals(20.00, $breakdown['total']);
    }

    public function test_base_fee_is_added_to_distance_cost(): void
    {
        $business = Business::factory()->create();
        $pricingTier = PricingTier::factory()->create([
            'business_type' => $business->business_type,
            'price_per_km' => 1.00,
            'base_fee' => 10.00,
            'minimum_cost' => 0.00,
            'effective_date' => now()->subDays(10),
        ]);

        $breakdown = $this->calculator->getCostBreakdown(20.00, $business);

        $this->assertEquals(20.00, $breakdown['distance_cost']);
        $this->assertEquals(10.00, $breakdown['base_fee']);
        $this->assertEquals(30.00, $breakdown['subtotal']);
    }

    public function test_cost_breakdown_includes_all_fields(): void
    {
        $business = Business::factory()->create();
        $pricingTier = PricingTier::factory()->create([
            'business_type' => $business->business_type,
            'effective_date' => now()->subDays(10),
        ]);

        $breakdown = $this->calculator->getCostBreakdown(50.00, $business);

        $requiredFields = [
            'distance_km',
            'price_per_km',
            'distance_cost',
            'base_fee',
            'subtotal',
            'discount_percent',
            'discount_amount',
            'after_discount',
            'minimum_cost',
            'minimum_applied',
            'before_tax',
            'tax_rate',
            'tax_amount',
            'total',
            'currency',
            'pricing_tier_used',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $breakdown, "Missing field: {$field}");
        }
    }
}
