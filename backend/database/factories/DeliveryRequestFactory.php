<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryRequestStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRequest>
 */
class DeliveryRequestFactory extends Factory
{
    protected $model = DeliveryRequest::class;

    public function definition(): array
    {
        $totalKm = fake()->randomFloat(2, 10, 100);
        $pricePerKm = 0.50;

        return [
            'business_id' => Business::factory(),
            'status' => DeliveryRequestStatus::Pending,
            'total_km' => $totalKm,
            'estimated_cost' => round($totalKm * $pricePerKm, 2),
            'actual_km' => null,
            'actual_cost' => null,
            'optimized_route' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'requested_at' => now(),
            'completed_at' => null,
        ];
    }

    /**
     * Accepted request.
     */
    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRequestStatus::Accepted,
        ]);
    }

    /**
     * In-progress request.
     */
    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRequestStatus::InProgress,
        ]);
    }

    /**
     * Completed request.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => DeliveryRequestStatus::Completed,
            'actual_km' => $attrs['total_km'] * fake()->randomFloat(2, 0.9, 1.1),
            'actual_cost' => ($attrs['total_km'] * fake()->randomFloat(2, 0.9, 1.1)) * 0.50,
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancelled request.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRequestStatus::Cancelled,
        ]);
    }

    /**
     * Request with optimized route.
     */
    public function withRoute(): static
    {
        return $this->state(fn () => [
            'optimized_route' => [
                'polyline' => 'encodedPolylineStringHere',
                'waypoint_order' => [0, 2, 1, 3],
            ],
        ]);
    }
}
