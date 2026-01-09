<?php

namespace Database\Factories;

use App\Models\WasteCollection;
use App\Models\WasteCollectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WasteCollectionItem>
 */
class WasteCollectionItemFactory extends Factory
{
    protected $model = WasteCollectionItem::class;

    public function definition(): array
    {
        $quantityDelivered = fake()->numberBetween(5, 50);
        $piecesWaste = fake()->numberBetween(0, $quantityDelivered);

        return [
            'waste_collection_id' => WasteCollection::factory(),
            'destination_item_id' => null, // Optional link to original delivery
            'order_item_id' => fake()->unique()->bothify('ITEM-###??'),
            'product_name' => fake()->word(),
            'quantity_delivered' => $quantityDelivered,
            'delivered_at' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'expires_at' => fake()->dateTimeBetween('-5 days', '+5 days')->format('Y-m-d'),
            'pieces_waste' => $piecesWaste,
            'notes' => null,
        ];
    }

    public function fullWaste(): self
    {
        return $this->state(fn (array $attributes) => [
            'pieces_waste' => $attributes['quantity_delivered'], // All wasted
        ]);
    }

    public function noWaste(): self
    {
        return $this->state(fn (array $attributes) => [
            'pieces_waste' => 0, // None wasted (all sold)
        ]);
    }

    public function partialWaste(): self
    {
        return $this->state(fn (array $attributes) => [
            'pieces_waste' => intval($attributes['quantity_delivered'] / 2), // 50% wasted
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-10 days', '-1 day')->format('Y-m-d'),
        ]);
    }

    public function notExpired(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
        ]);
    }
}
