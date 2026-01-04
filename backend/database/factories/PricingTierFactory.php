<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\PricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingTier>
 */
class PricingTierFactory extends Factory
{
    protected $model = PricingTier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Standard', 'Premium', 'Economy', 'Express']),
            'business_type' => fake()->optional(0.5)->randomElement(BusinessType::cases()),
            'price_per_km' => fake()->randomFloat(4, 0.3, 1.5),
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_active' => true,
        ];
    }

    /**
     * Default pricing tier (applies to all business types).
     */
    public function default(): static
    {
        return $this->state(fn () => [
            'name' => 'Standard',
            'business_type' => null,
            'price_per_km' => 0.50,
            'effective_date' => now()->subYear(),
        ]);
    }

    /**
     * Pricing for bulk order businesses.
     */
    public function forBulkOrder(): static
    {
        return $this->state(fn () => [
            'business_type' => BusinessType::BulkOrder,
        ]);
    }

    /**
     * Pricing for pickup businesses.
     */
    public function forPickup(): static
    {
        return $this->state(fn () => [
            'business_type' => BusinessType::Pickup,
        ]);
    }

    /**
     * Inactive pricing tier.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Future pricing tier (not yet effective).
     */
    public function future(): static
    {
        return $this->state(fn () => [
            'effective_date' => fake()->dateTimeBetween('+1 month', '+1 year'),
        ]);
    }
}
