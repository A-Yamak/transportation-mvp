# Developer 2: Revenue Management (Pricing & Cost Calculation)

**Date**: 2026-01-04
**Phase**: 2.1 - Pricing Service Implementation
**Estimated Time**: 4-5 hours
**Priority**: HIGH (Required for delivery request cost calculation)

---

## 🎯 Mission

Extract pricing logic from the `PricingTier` model into a dedicated service layer and build a comprehensive cost calculation system for delivery requests and trips. This service will be used when creating delivery requests (Phase 3) to calculate costs based on distance and business type.

---

## 📋 Task Overview

| Task | Files | Tests Required | Time Estimate |
|------|-------|----------------|---------------|
| 1. Create pricing config | 1 config file | - | 15 min |
| 2. Create CostCalculator service | 1 service | Unit tests | 90 min |
| 3. Write feature tests | 1 test file | Feature tests | 60 min |
| 4. Write unit tests | 1 test file | Unit tests | 60 min |
| 5. Update .env.example | 1 file | - | 10 min |
| **Total** | **5 files** | **80%+ coverage** | **4-5 hours** |

---

## 🗂️ Files You Will Create (Zero Conflicts)

### Configuration (1 file)
```
config/pricing.php
```

### Service (1 file)
```
app/Services/Pricing/CostCalculator.php
```

### Tests (2 files)
```
tests/Feature/PricingCalculationTest.php
tests/Unit/Services/Pricing/CostCalculatorTest.php
```

### Environment (1 file to edit)
```
.env.example (add pricing variables only - no conflicts)
```

---

## 📐 Technical Specifications

### Configuration File

#### config/pricing.php

**Purpose**: Centralize all pricing-related configuration

**File**: `config/pricing.php`

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Price Per Kilometer
    |--------------------------------------------------------------------------
    |
    | This is the fallback price per kilometer when no pricing tier is found
    | for a specific business type. Typically used for new business types or
    | when pricing tiers haven't been configured yet.
    |
    */
    'default_price_per_km' => (float) env('DEFAULT_PRICE_PER_KM', 0.50),

    /*
    |--------------------------------------------------------------------------
    | Minimum Trip Cost
    |--------------------------------------------------------------------------
    |
    | The minimum cost for any delivery, regardless of distance.
    | Ensures profitability for very short trips.
    |
    */
    'minimum_trip_cost' => (float) env('MINIMUM_TRIP_COST', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Base Fee
    |--------------------------------------------------------------------------
    |
    | Fixed fee added to every delivery in addition to distance-based pricing.
    | Set to 0 if you only want distance-based pricing.
    |
    */
    'base_fee' => (float) env('BASE_FEE', 0.00),

    /*
    |--------------------------------------------------------------------------
    | Pricing Tiers by Business Type
    |--------------------------------------------------------------------------
    |
    | Default pricing tiers for different business types.
    | These can be overridden by database pricing tiers.
    |
    */
    'business_type_rates' => [
        'bulk_order' => [
            'price_per_km' => 0.45,
            'base_fee' => 2.00,
            'minimum_cost' => 10.00,
        ],
        'pickup' => [
            'price_per_km' => 0.60,
            'base_fee' => 5.00,
            'minimum_cost' => 15.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Distance-Based Tier Discounts
    |--------------------------------------------------------------------------
    |
    | Apply discounts for longer trips to encourage bulk usage.
    | Format: ['min_km' => discount_percentage]
    |
    */
    'distance_discounts' => [
        50 => 0.05,  // 5% discount for trips over 50 KM
        100 => 0.10, // 10% discount for trips over 100 KM
        200 => 0.15, // 15% discount for trips over 200 KM
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | Tax rate to apply to delivery costs (as decimal).
    | Set to 0 if tax is not applicable or handled separately.
    |
    */
    'tax_rate' => (float) env('TAX_RATE', 0.00),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for pricing display and calculations.
    |
    */
    'currency' => env('CURRENCY', 'JOD'), // Jordanian Dinar

    /*
    |--------------------------------------------------------------------------
    | Currency Symbol
    |--------------------------------------------------------------------------
    |
    | Symbol to display with pricing.
    |
    */
    'currency_symbol' => env('CURRENCY_SYMBOL', 'JOD'),

    /*
    |--------------------------------------------------------------------------
    | Rounding Precision
    |--------------------------------------------------------------------------
    |
    | Number of decimal places to round prices to.
    |
    */
    'rounding_precision' => 2,
];
```

---

### Service Specification

#### CostCalculator Service

**File**: `app/Services/Pricing/CostCalculator.php`

**Purpose**: Calculate costs for delivery requests and trips based on distance, business type, and pricing tiers

**Required Methods**:

```php
<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\PricingTier;
use App\Models\Trip;
use InvalidArgumentException;

/**
 * Handles cost calculations for delivery requests and trips.
 *
 * Pricing Strategy:
 * 1. Find applicable pricing tier (by business type and effective date)
 * 2. Calculate: (distance_km × price_per_km) + base_fee
 * 3. Apply distance-based discounts if applicable
 * 4. Ensure minimum trip cost is met
 * 5. Add tax if configured
 */
class CostCalculator
{
    /**
     * Calculate the cost for a delivery request.
     *
     * @param DeliveryRequest $deliveryRequest Delivery request with total_km set
     * @return float Total cost (rounded to 2 decimals)
     * @throws InvalidArgumentException if total_km is not set
     */
    public function calculateDeliveryRequestCost(DeliveryRequest $deliveryRequest): float
    {
        if (! $deliveryRequest->total_km || $deliveryRequest->total_km <= 0) {
            throw new InvalidArgumentException('Delivery request must have total_km set');
        }

        $business = $deliveryRequest->business;
        $distanceKm = $deliveryRequest->total_km;

        return $this->calculateCost($distanceKm, $business);
    }

    /**
     * Calculate the cost for a trip.
     *
     * @param Trip $trip Trip with actual_km_driven set
     * @return float Total cost (rounded to 2 decimals)
     * @throws InvalidArgumentException if actual_km_driven is not set
     */
    public function calculateTripCost(Trip $trip): float
    {
        if (! $trip->actual_km_driven || $trip->actual_km_driven <= 0) {
            throw new InvalidArgumentException('Trip must have actual_km_driven set');
        }

        $business = $trip->deliveryRequest->business;
        $distanceKm = $trip->actual_km_driven;

        return $this->calculateCost($distanceKm, $business);
    }

    /**
     * Get the applicable pricing tier for a business.
     *
     * Finds the most recent pricing tier for the business type that is
     * effective as of today (or specified date).
     *
     * @param Business $business
     * @param string|null $asOfDate Date to check pricing (default: today)
     * @return PricingTier|null Applicable tier or null if not found
     */
    public function getApplicableTier(Business $business, ?string $asOfDate = null): ?PricingTier
    {
        $date = $asOfDate ?? now()->toDateString();

        return PricingTier::where('business_type', $business->business_type)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();
    }

    /**
     * Estimate cost by distance and business type (without Business model).
     *
     * Useful for quick estimates before creating delivery requests.
     *
     * @param float $distanceKm Distance in kilometers
     * @param BusinessType $businessType Type of business
     * @param string|null $asOfDate Date to check pricing (default: today)
     * @return float Estimated cost
     */
    public function estimateCostByDistance(
        float $distanceKm,
        BusinessType $businessType,
        ?string $asOfDate = null
    ): float {
        $date = $asOfDate ?? now()->toDateString();

        $tier = PricingTier::where('business_type', $businessType)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        $pricePerKm = $tier?->price_per_km ?? $this->getDefaultPricePerKm($businessType);
        $baseFee = $tier?->base_fee ?? $this->getDefaultBaseFee($businessType);

        $cost = ($distanceKm * $pricePerKm) + $baseFee;

        // Apply distance discounts
        $cost = $this->applyDistanceDiscount($cost, $distanceKm);

        // Apply minimum cost
        $minimumCost = $tier?->minimum_cost ?? $this->getDefaultMinimumCost($businessType);
        $cost = max($cost, $minimumCost);

        // Apply tax
        $cost = $this->applyTax($cost);

        return $this->roundCost($cost);
    }

    /**
     * Calculate cost for a given distance and business.
     *
     * @param float $distanceKm Distance in kilometers
     * @param Business $business Business for pricing tier lookup
     * @return float Total cost
     */
    protected function calculateCost(float $distanceKm, Business $business): float
    {
        $tier = $this->getApplicableTier($business);

        // Get pricing parameters
        $pricePerKm = $tier?->price_per_km ?? $this->getDefaultPricePerKm($business->business_type);
        $baseFee = $tier?->base_fee ?? $this->getDefaultBaseFee($business->business_type);

        // Calculate base cost
        $cost = ($distanceKm * $pricePerKm) + $baseFee;

        // Apply distance discounts
        $cost = $this->applyDistanceDiscount($cost, $distanceKm);

        // Apply minimum cost
        $minimumCost = $tier?->minimum_cost ?? $this->getDefaultMinimumCost($business->business_type);
        $cost = max($cost, $minimumCost);

        // Apply tax
        $cost = $this->applyTax($cost);

        return $this->roundCost($cost);
    }

    /**
     * Apply distance-based discounts.
     *
     * Longer trips get percentage discounts based on config.
     *
     * @param float $cost Current cost
     * @param float $distanceKm Distance in kilometers
     * @return float Cost after discount
     */
    protected function applyDistanceDiscount(float $cost, float $distanceKm): float
    {
        $discounts = config('pricing.distance_discounts', []);

        // Find the highest applicable discount
        $applicableDiscount = 0;
        foreach ($discounts as $minKm => $discountPercentage) {
            if ($distanceKm >= $minKm) {
                $applicableDiscount = max($applicableDiscount, $discountPercentage);
            }
        }

        if ($applicableDiscount > 0) {
            $cost = $cost * (1 - $applicableDiscount);
        }

        return $cost;
    }

    /**
     * Apply tax to cost.
     *
     * @param float $cost Cost before tax
     * @return float Cost after tax
     */
    protected function applyTax(float $cost): float
    {
        $taxRate = config('pricing.tax_rate', 0);

        if ($taxRate > 0) {
            return $cost * (1 + $taxRate);
        }

        return $cost;
    }

    /**
     * Round cost to configured precision.
     *
     * @param float $cost Cost to round
     * @return float Rounded cost
     */
    protected function roundCost(float $cost): float
    {
        $precision = config('pricing.rounding_precision', 2);
        return round($cost, $precision);
    }

    /**
     * Get default price per kilometer for business type.
     *
     * @param BusinessType $businessType
     * @return float Default price per KM
     */
    protected function getDefaultPricePerKm(BusinessType $businessType): float
    {
        $typeRates = config('pricing.business_type_rates', []);

        if (isset($typeRates[$businessType->value]['price_per_km'])) {
            return (float) $typeRates[$businessType->value]['price_per_km'];
        }

        return config('pricing.default_price_per_km', 0.50);
    }

    /**
     * Get default base fee for business type.
     *
     * @param BusinessType $businessType
     * @return float Default base fee
     */
    protected function getDefaultBaseFee(BusinessType $businessType): float
    {
        $typeRates = config('pricing.business_type_rates', []);

        if (isset($typeRates[$businessType->value]['base_fee'])) {
            return (float) $typeRates[$businessType->value]['base_fee'];
        }

        return config('pricing.base_fee', 0.00);
    }

    /**
     * Get default minimum cost for business type.
     *
     * @param BusinessType $businessType
     * @return float Default minimum cost
     */
    protected function getDefaultMinimumCost(BusinessType $businessType): float
    {
        $typeRates = config('pricing.business_type_rates', []);

        if (isset($typeRates[$businessType->value]['minimum_cost'])) {
            return (float) $typeRates[$businessType->value]['minimum_cost'];
        }

        return config('pricing.minimum_trip_cost', 5.00);
    }

    /**
     * Get breakdown of cost calculation for display/debugging.
     *
     * @param float $distanceKm
     * @param Business $business
     * @return array Breakdown of cost calculation
     */
    public function getCostBreakdown(float $distanceKm, Business $business): array
    {
        $tier = $this->getApplicableTier($business);

        $pricePerKm = $tier?->price_per_km ?? $this->getDefaultPricePerKm($business->business_type);
        $baseFee = $tier?->base_fee ?? $this->getDefaultBaseFee($business->business_type);
        $minimumCost = $tier?->minimum_cost ?? $this->getDefaultMinimumCost($business->business_type);

        $distanceCost = $distanceKm * $pricePerKm;
        $subtotal = $distanceCost + $baseFee;

        // Calculate discount
        $discountPercent = 0;
        $discounts = config('pricing.distance_discounts', []);
        foreach ($discounts as $minKm => $discountPercentage) {
            if ($distanceKm >= $minKm) {
                $discountPercent = max($discountPercent, $discountPercentage);
            }
        }
        $discountAmount = $subtotal * $discountPercent;
        $afterDiscount = $subtotal - $discountAmount;

        // Apply minimum
        $beforeTax = max($afterDiscount, $minimumCost);
        $minimumApplied = $beforeTax === $minimumCost && $afterDiscount < $minimumCost;

        // Calculate tax
        $taxRate = config('pricing.tax_rate', 0);
        $taxAmount = $beforeTax * $taxRate;
        $total = $beforeTax + $taxAmount;

        return [
            'distance_km' => $distanceKm,
            'price_per_km' => $pricePerKm,
            'distance_cost' => $this->roundCost($distanceCost),
            'base_fee' => $baseFee,
            'subtotal' => $this->roundCost($subtotal),
            'discount_percent' => $discountPercent * 100,
            'discount_amount' => $this->roundCost($discountAmount),
            'after_discount' => $this->roundCost($afterDiscount),
            'minimum_cost' => $minimumCost,
            'minimum_applied' => $minimumApplied,
            'before_tax' => $this->roundCost($beforeTax),
            'tax_rate' => $taxRate * 100,
            'tax_amount' => $this->roundCost($taxAmount),
            'total' => $this->roundCost($total),
            'currency' => config('pricing.currency', 'JOD'),
            'pricing_tier_used' => $tier ? [
                'id' => $tier->id,
                'effective_date' => $tier->effective_date->toDateString(),
            ] : null,
        ];
    }
}
```

---

## 🧪 Testing Requirements

### Feature Test

**File**: `tests/Feature/PricingCalculationTest.php`

**Required Test Cases**:

```php
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
            'business_type' => BusinessType::bulk_order,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
            'business_type' => BusinessType::bulk_order,
        ]);

        $deliveryRequest = DeliveryRequest::factory()->for($business)->create([
            'total_km' => 10.00,
        ]);

        $cost = $this->calculator->calculateDeliveryRequestCost($deliveryRequest);

        // Should use config defaults
        // (10 km × 0.45) + 2.00 = 6.50 (from config/pricing.php business_type_rates)
        $this->assertEquals(6.50, $cost);
    }

    public function test_applies_minimum_cost_when_calculated_cost_is_too_low(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::bulk_order,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
            'business_type' => BusinessType::bulk_order,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
                'actual_km_driven' => 25.00, // Driver took longer route
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
            'business_type' => BusinessType::bulk_order,
        ]);

        // Old tier (should not be used)
        PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
            'price_per_km' => 0.40,
            'effective_date' => now()->subDays(60),
        ]);

        // Current tier (should be used)
        $currentTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
            'price_per_km' => 0.50,
            'effective_date' => now()->subDays(10),
        ]);

        // Future tier (should not be used)
        PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
            'business_type' => BusinessType::pickup,
            'price_per_km' => 0.70,
            'base_fee' => 5.00,
            'minimum_cost' => 15.00,
            'effective_date' => now()->subDays(10),
        ]);

        $cost = $this->calculator->estimateCostByDistance(
            distanceKm: 30.00,
            businessType: BusinessType::pickup
        );

        // (30 km × 0.70) + 5.00 = 26.00
        $this->assertEquals(26.00, $cost);
    }

    public function test_get_cost_breakdown_returns_detailed_calculation(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::bulk_order,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
            'business_type' => BusinessType::bulk_order,
        ]);

        $pickupBusiness = Business::factory()->create([
            'business_type' => BusinessType::pickup,
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
            'business_type' => BusinessType::bulk_order,
        ]);

        $pricingTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
```

---

### Unit Test

**File**: `tests/Unit/Services/Pricing/CostCalculatorTest.php`

**Required Test Cases**:

```php
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
            'business_type' => BusinessType::bulk_order,
        ]);

        // Don't create any pricing tiers
        $tier = $this->calculator->getApplicableTier($business);

        $this->assertNull($tier);
    }

    public function test_uses_most_recent_effective_tier(): void
    {
        $business = Business::factory()->create([
            'business_type' => BusinessType::bulk_order,
        ]);

        // Create tiers with different effective dates
        PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
            'price_per_km' => 0.30,
            'effective_date' => now()->subDays(90),
        ]);

        $latestTier = PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
            'price_per_km' => 0.50,
            'effective_date' => now()->subDays(30),
        ]);

        PricingTier::factory()->create([
            'business_type' => BusinessType::bulk_order,
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
```

---

## 📝 Update .env.example

Add the following to `.env.example`:

```env
# Pricing Configuration
DEFAULT_PRICE_PER_KM=0.50
MINIMUM_TRIP_COST=5.00
BASE_FEE=0.00
TAX_RATE=0.00
CURRENCY=JOD
CURRENCY_SYMBOL=JOD
```

---

## ✅ Completion Checklist

### Phase 1: Configuration (30 min)
- [ ] Create `config/pricing.php` with all settings
- [ ] Test config loads: `php artisan tinker` → `config('pricing.default_price_per_km')`
- [ ] Update `.env.example` with pricing variables

### Phase 2: Service Implementation (2 hours)
- [ ] Create `app/Services/Pricing/CostCalculator.php`
- [ ] Implement `calculateDeliveryRequestCost()` method
- [ ] Implement `calculateTripCost()` method
- [ ] Implement `getApplicableTier()` method
- [ ] Implement `estimateCostByDistance()` method
- [ ] Implement `getCostBreakdown()` method
- [ ] Implement protected helper methods (applyDiscount, applyTax, etc.)

### Phase 3: Testing (2.5 hours)
- [ ] Create `tests/Feature/PricingCalculationTest.php`
- [ ] Write test: calculates with pricing tier
- [ ] Write test: uses defaults when no tier
- [ ] Write test: applies minimum cost
- [ ] Write test: applies distance discount
- [ ] Write test: calculates trip cost
- [ ] Write test: throws exception on missing KM
- [ ] Write test: gets applicable tier by date
- [ ] Write test: estimates cost without business model
- [ ] Write test: gets cost breakdown
- [ ] Write test: different business types have different rates
- [ ] Write test: rounds to precision
- [ ] Create `tests/Unit/Services/Pricing/CostCalculatorTest.php`
- [ ] Write unit tests for all protected methods
- [ ] Run `php artisan test` - all tests pass

### Phase 4: Quality Assurance (30 min)
- [ ] PSR-12 compliance
- [ ] PHPDoc on all public methods
- [ ] Type hints on all parameters and return types
- [ ] No debug statements
- [ ] Code coverage >80%

---

## 🔧 Commands You'll Use

```bash
# Create config file (manual - just create the file)

# Create service directory
mkdir -p app/Services/Pricing

# Create test files
php artisan make:test PricingCalculationTest
php artisan make:test Services/Pricing/CostCalculatorTest --unit

# Run tests
php artisan test --filter=PricingCalculationTest
php artisan test --filter=CostCalculatorTest

# Test in tinker
php artisan tinker
>>> config('pricing.default_price_per_km')
>>> $business = \App\Models\Business::factory()->create();
>>> $request = \App\Models\DeliveryRequest::factory()->for($business)->create(['total_km' => 25]);
>>> $calculator = app(\App\Services\Pricing\CostCalculator::class);
>>> $calculator->calculateDeliveryRequestCost($request);
```

---

## 🚫 What NOT to Touch

**Do NOT edit these files** (to avoid conflicts):
- ❌ Existing models (only read from PricingTier and Business)
- ❌ Migrations
- ❌ Routes
- ❌ Controllers
- ❌ Other config files (only create pricing.php)

---

## 🎯 Success Criteria

By end of day, you should have:
- ✅ `config/pricing.php` created with all settings
- ✅ `CostCalculator` service with 5+ public methods
- ✅ All tests passing (green)
- ✅ Code coverage >80%
- ✅ PSR-12 compliant
- ✅ Full PHPDoc coverage
- ✅ `.env.example` updated

---

## 📊 Integration Points

**Your service will be used by:**
- **Phase 3**: DeliveryRequest API controller (to calculate estimated cost)
- **Developer 3**: RouteOptimizer service (after calculating distance)
- **Developer 1**: LedgerService (for recording trip revenue)

**Your service depends on:**
- `PricingTier` model (existing - just read from it)
- `Business` model (existing - just read from it)
- `DeliveryRequest` model (existing - just read from it)
- `Trip` model (existing - just read from it)

---

**Good luck! You're building the revenue engine of the system.**
