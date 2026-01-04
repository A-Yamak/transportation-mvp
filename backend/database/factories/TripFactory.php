<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'driver_id' => Driver::factory(),
            'vehicle_id' => Vehicle::factory(),
            'status' => TripStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
            'actual_km' => null,
        ];
    }

    /**
     * Trip in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::InProgress,
            'started_at' => now()->subHours(fake()->numberBetween(1, 4)),
        ]);
    }

    /**
     * Completed trip.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::Completed,
            'started_at' => now()->subHours(fake()->numberBetween(4, 8)),
            'completed_at' => now()->subHours(fake()->numberBetween(0, 2)),
            'actual_km' => fake()->randomFloat(2, 20, 80),
        ]);
    }

    /**
     * Cancelled trip.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => TripStatus::Cancelled,
        ]);
    }

    /**
     * Today's trip.
     */
    public function today(): static
    {
        return $this->state(fn () => [
            'created_at' => now(),
        ]);
    }
}
