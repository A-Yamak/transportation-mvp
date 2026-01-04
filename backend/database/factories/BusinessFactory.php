<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'business_type' => fake()->randomElement(BusinessType::cases()),
            'api_key' => 'bus_' . Str::random(40),
            'callback_url' => fake()->optional(0.7)->url(),
            'callback_api_key' => fake()->optional(0.7)->password(32),
            'is_active' => true,
        ];
    }

    /**
     * Business with bulk order type.
     */
    public function bulkOrder(): static
    {
        return $this->state(fn () => [
            'business_type' => BusinessType::BulkOrder,
        ]);
    }

    /**
     * Business with pickup type.
     */
    public function pickup(): static
    {
        return $this->state(fn () => [
            'business_type' => BusinessType::Pickup,
        ]);
    }

    /**
     * Inactive business.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Business with callback configured.
     */
    public function withCallback(): static
    {
        return $this->state(fn () => [
            'callback_url' => fake()->url(),
            'callback_api_key' => Str::random(32),
        ]);
    }
}
