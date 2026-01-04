<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DestinationStatus;
use App\Enums\FailureReason;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Destination>
 */
class DestinationFactory extends Factory
{
    protected $model = Destination::class;

    // Amman, Jordan coordinates for realistic data
    private const AMMAN_LAT = 31.9539;
    private const AMMAN_LNG = 35.9106;

    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'external_id' => 'ORD-' . fake()->unique()->numerify('######'),
            'address' => fake()->streetAddress() . ', عمان، الأردن',
            'lat' => self::AMMAN_LAT + fake()->randomFloat(4, -0.1, 0.1),
            'lng' => self::AMMAN_LNG + fake()->randomFloat(4, -0.1, 0.1),
            'sequence_order' => $sequence,
            'status' => DestinationStatus::Pending,
            'notes' => fake()->optional(0.3)->sentence(),
            'recipient_name' => null,
            'failure_reason' => null,
            'failure_notes' => null,
            'arrived_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Arrived at destination.
     */
    public function arrived(): static
    {
        return $this->state(fn () => [
            'status' => DestinationStatus::Arrived,
            'arrived_at' => now()->subMinutes(fake()->numberBetween(1, 10)),
        ]);
    }

    /**
     * Completed delivery.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => DestinationStatus::Completed,
            'arrived_at' => now()->subMinutes(fake()->numberBetween(10, 30)),
            'completed_at' => now()->subMinutes(fake()->numberBetween(1, 5)),
            'recipient_name' => fake()->name(),
        ]);
    }

    /**
     * Failed delivery.
     */
    public function failed(?FailureReason $reason = null): static
    {
        return $this->state(fn () => [
            'status' => DestinationStatus::Failed,
            'arrived_at' => now()->subMinutes(fake()->numberBetween(10, 30)),
            'completed_at' => now()->subMinutes(fake()->numberBetween(1, 5)),
            'failure_reason' => $reason ?? fake()->randomElement(FailureReason::cases()),
            'failure_notes' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /**
     * Reset sequence for new batch.
     */
    public function resetSequence(): static
    {
        return $this->state(fn () => [
            'sequence_order' => 1,
        ]);
    }

    /**
     * With specific sequence order.
     */
    public function sequence(int $order): static
    {
        return $this->state(fn () => [
            'sequence_order' => $order,
        ]);
    }
}
