<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\PricingTier;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Pricing\CostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected CostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(CostCalculator::class);
    }

    public function test_calculates_delivery_request_cost_with_pricing_tier(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.50,
            'base_fee' => 2.00,
            'minimum_cost' => 5.00,
            'effective_date' => now()->subDays(10),
        ]);

        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 20.00,
        ]);

        $cost = $this->calculator->calculateDeliveryRequestCost($deliveryRequest);

        // (20 km × 0.50) + 2.00 = 12.00
        $this->assertEquals(12.00, $cost);
    }

    public function test_uses_default_pricing_when_no_tier_exists(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 10.00,
        ]);

        $cost = $this->calculator->calculateDeliveryRequestCost($deliveryRequest);

        // Should use config defaults
        // (10 km × 0.45) + 2.00 = 6.50, but minimum_cost of 10.00 applies
        $this->assertEquals(10.00, $cost);
    }

    public function test_applies_minimum_cost_when_calculated_cost_is_too_low(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.50,
            'base_fee' => 0.00,
            'minimum_cost' => 10.00,
            'effective_date' => now()->subDays(10),
        ]);

        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 5.00, // Would only be 2.50 without minimum
        ]);

        $cost = $this->calculator->calculateDeliveryRequestCost($deliveryRequest);

        // Should apply minimum cost of 10.00
        $this->assertEquals(10.00, $cost);
    }

    public function test_applies_distance_discount_for_long_trips(): void
    {
        config(['pricing.distance_discounts' => [
            50 => 0.10, // 10% discount for trips over 50 KM
        ]]);

        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 1.00,
            'base_fee' => 0.00,
            'minimum_cost' => 0.00,
            'effective_date' => now()->subDays(10),
        ]);

        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 60.00,
        ]);

        $cost = $this->calculator->calculateDeliveryRequestCost($deliveryRequest);

        // (60 km × 1.00) = 60.00, then 10% discount = 54.00
        $this->assertEquals(54.00, $cost);
    }

    public function test_calculates_trip_cost_based_on_actual_km_driven(): void
    {
        $business = Business::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 20.00,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => $business->business_type,
            'price_per_km' => 0.60,
            'base_fee' => 3.00,
            'minimum_cost' => 5.00,
            'effective_date' => now()->subDays(10),
        ]);

        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($driver)
            ->for($vehicle)
            ->create([
                'actual_km' => 25.00, // Driver took longer route
            ]);

        $cost = $this->calculator->calculateTripCost($trip);

        // (25 km × 0.60) + 3.00 = 18.00
        $this->assertEquals(18.00, $cost);
    }

    public function test_throws_exception_when_delivery_request_has_no_total_km(): void
    {
        $business = Business::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delivery request must have total_km set');

        $this->calculator->calculateDeliveryRequestCost($deliveryRequest);
    }

    public function test_gets_applicable_pricing_tier_by_effective_date(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        // Old tier (should not be used)
        PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.40,
            'effective_date' => now()->subDays(60),
        ]);

        // Current tier (should be used)
        $currentTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.50,
            'effective_date' => now()->subDays(10),
        ]);

        // Future tier (should not be used)
        PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.60,
            'effective_date' => now()->addDays(10),
        ]);

        $tier = $this->calculator->getApplicableTier($business);

        $this->assertEquals($currentTier->id, $tier->id);
        $this->assertEquals(0.50, $tier->price_per_km);
    }

    public function test_estimate_cost_by_distance_without_business_model(): void
    {
        PricingTier::factory()->create([
            'business_type' => BusinessType::Pickup,
            'price_per_km' => 0.70,
            'base_fee' => 5.00,
            'minimum_cost' => 15.00,
            'effective_date' => now()->subDays(10),
        ]);

        $cost = $this->calculator->estimateCostByDistance(
            distanceKm: 30.00,
            businessType: BusinessType::Pickup
        );

        // (30 km × 0.70) + 5.00 = 26.00
        $this->assertEquals(26.00, $cost);
    }

    public function test_get_cost_breakdown_returns_detailed_calculation(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 1.00,
            'base_fee' => 5.00,
            'minimum_cost' => 10.00,
            'effective_date' => now()->subDays(10),
        ]);

        $breakdown = $this->calculator->getCostBreakdown(20.00, $business);

        $this->assertIsArray($breakdown);
        $this->assertEquals(20.00, $breakdown['distance_km']);
        $this->assertEquals(1.00, $breakdown['price_per_km']);
        $this->assertEquals(20.00, $breakdown['distance_cost']);
        $this->assertEquals(5.00, $breakdown['base_fee']);
        $this->assertEquals(25.00, $breakdown['subtotal']);
        $this->assertEquals(25.00, $breakdown['total']);
        $this->assertArrayHasKey('pricing_tier_used', $breakdown);
        $this->assertEquals($pricingTier->id, $breakdown['pricing_tier_used']['id']);
    }

    public function test_different_business_types_use_different_default_rates(): void
    {
        // Create businesses with different types
        $bulkOrderBusiness = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $pickupBusiness = Business::factory()->create([
            'business_type' => BusinessType::Pickup,
        ]);

        // Create delivery requests with same distance
        $bulkOrderRequest = DeliveryRequest::factory()->for($bulkOrderBusiness)->create([
            'total_km' => 10.00,
        ]);

        $pickupRequest = DeliveryRequest::factory()->for($pickupBusiness)->create([
            'total_km' => 10.00,
        ]);

        $bulkOrderCost = $this->calculator->calculateDeliveryRequestCost($bulkOrderRequest);
        $pickupCost = $this->calculator->calculateDeliveryRequestCost($pickupRequest);

        // Pickup should be more expensive (config: pickup = 0.60/km, bulk = 0.45/km)
        $this->assertGreaterThan($bulkOrderCost, $pickupCost);
    }

    public function test_rounds_cost_to_configured_precision(): void
    {
        config(['pricing.rounding_precision' => 2]);

        $business = Business::factory()->create([
            'business_type' => BusinessType::BulkOrder,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::BulkOrder,
            'price_per_km' => 0.333,
            'base_fee' => 0.00,
            'minimum_cost' => 0.00,
            'effective_date' => now()->subDays(10),
        ]);

        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 10.00,
        ]);

        $cost = $this->calculator->calculateDeliveryRequestCost($deliveryRequest);

        // 10 × 0.333 = 3.33 (rounded to 2 decimals)
        $this->assertEquals(3.33, $cost);
    }
}
