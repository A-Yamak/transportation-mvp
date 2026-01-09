<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\User;
use App\Models\WasteCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteCollection>
 */
class WasteCollectionFactory extends Factory
{
    protected $model = WasteCollection::class;

    public function definition(): array
    {
        $shop = Shop::factory()->create();

        return [
            'shop_id' => $shop->id,
            'trip_id' => null, // Optional, waste can be logged without trip
            'driver_id' => null, // Optional
            'business_id' => $shop->business_id,
            'collection_date' => fake()->date(),
            'total_items_count' => 0, // Updated when items added
            'collected_at' => null, // Not collected yet
            'driver_notes' => null,
        ];
    }

    public function collected(): self
    {
        return $this->state(fn (array $attributes) => [
            'collected_at' => fake()->dateTimeBetween('-1 day'),
            'driver_id' => User::factory(),
            'trip_id' => Trip::factory(),
        ]);
    }

    public function withDriver(): self
    {
        return $this->state(fn (array $attributes) => [
            'driver_id' => User::factory(),
        ]);
    }

    public function withTrip(): self
    {
        return $this->state(fn (array $attributes) => [
            'trip_id' => Trip::factory(),
        ]);
    }
}
