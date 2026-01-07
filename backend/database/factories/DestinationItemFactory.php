<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemDeliveryReason;
use App\Models\Destination;
use App\Models\DestinationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestinationItem>
 */
class DestinationItemFactory extends Factory
{
    protected $model = DestinationItem::class;

    public function definition(): array
    {
        $quantityOrdered = fake()->numberBetween(5, 50);

        return [
            'destination_id' => Destination::factory(),
            'order_item_id' => (string) fake()->unique()->numberBetween(100, 9999),
            'quantity_ordered' => $quantityOrdered,
            'quantity_delivered' => $quantityOrdered, // Default: full delivery
            'delivery_reason' => null,
            'notes' => null,
        ];
    }

    /**
     * Item with full delivery.
     */
    public function fullyDelivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_delivered' => $attributes['quantity_ordered'],
            'delivery_reason' => null,
            'notes' => null,
        ]);
    }

    /**
     * Item with partial delivery.
     */
    public function partialDelivery(?ItemDeliveryReason $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_delivered' => fake()->numberBetween(1, max(1, $attributes['quantity_ordered'] - 1)),
            'delivery_reason' => $reason ?? fake()->randomElement(ItemDeliveryReason::cases()),
            'notes' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Item completely not delivered.
     */
    public function notDelivered(?ItemDeliveryReason $reason = null): static
    {
        return $this->state(fn () => [
            'quantity_delivered' => 0,
            'delivery_reason' => $reason ?? ItemDeliveryReason::CustomerRefused,
            'notes' => fake()->sentence(),
        ]);
    }

    /**
     * Damaged item.
     */
    public function damaged(?int $damagedCount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_delivered' => max(0, $attributes['quantity_ordered'] - ($damagedCount ?? fake()->numberBetween(1, 5))),
            'delivery_reason' => ItemDeliveryReason::DamagedInTransit,
            'notes' => fake()->optional(0.7)->sentence(),
        ]);
    }

    /**
     * With specific order item ID.
     */
    public function withOrderItemId(string $orderItemId): static
    {
        return $this->state(fn () => [
            'order_item_id' => $orderItemId,
        ]);
    }

    /**
     * With specific quantities.
     */
    public function withQuantities(int $ordered, int $delivered): static
    {
        return $this->state(fn () => [
            'quantity_ordered' => $ordered,
            'quantity_delivered' => $delivered,
        ]);
    }
}
