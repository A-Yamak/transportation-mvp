<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'external_shop_id' => fake()->unique()->bothify('SHOP-###??'),
            'name' => fake()->company(),
            'address' => fake()->address(),
            'lat' => fake()->latitude(31.5, 32.5), // Jordan latitude range
            'lng' => fake()->longitude(35.2, 36.5), // Jordan longitude range
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'track_waste' => fake()->boolean(30), // 30% of shops track waste
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function trackWaste(): self
    {
        return $this->state(fn (array $attributes) => [
            'track_waste' => true,
        ]);
    }

    public function noWasteTracking(): self
    {
        return $this->state(fn (array $attributes) => [
            'track_waste' => false,
        ]);
    }
}
