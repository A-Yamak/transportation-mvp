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
        $distanceKm = (float) $deliveryRequest->total_km;

        return $this->calculateCost($distanceKm, $business);
    }

    /**
     * Calculate the cost for a trip.
     *
     * @param Trip $trip Trip with actual_km set
     * @return float Total cost (rounded to 2 decimals)
     * @throws InvalidArgumentException if actual_km is not set
     */
    public function calculateTripCost(Trip $trip): float
    {
        if (! $trip->actual_km || $trip->actual_km <= 0) {
            throw new InvalidArgumentException('Trip must have actual_km set');
        }

        $business = $trip->deliveryRequest->business;
        $distanceKm = (float) $trip->actual_km;

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
        $cost = (float) max($cost, $minimumCost);

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
        $cost = (float) max($cost, $minimumCost);

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

        $pricePerKm = (float) ($tier?->price_per_km ?? $this->getDefaultPricePerKm($business->business_type));
        $baseFee = (float) ($tier?->base_fee ?? $this->getDefaultBaseFee($business->business_type));
        $minimumCost = (float) ($tier?->minimum_cost ?? $this->getDefaultMinimumCost($business->business_type));

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
        $beforeTax = (float) max($afterDiscount, $minimumCost);
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
