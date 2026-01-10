<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['trip_assigned', 'trip_reassigned', 'payment_received', 'action_required']),
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'data' => [
                'action' => fake()->randomElement(['open_trip', 'open_earnings', 'view_details']),
                'trip_id' => fake()->uuid(),
            ],
            'status' => 'sent',
            'sent_at' => now(),
        ];
    }

    /**
     * Trip assigned notification
     */
    public function tripAssigned(User $driver = null, array $tripData = []): self
    {
        return $this->state(function (array $attributes) use ($driver, $tripData) {
            $destinationsCount = $tripData['destinations_count'] ?? 3;
            return [
                'user_id' => $driver?->id ?? User::factory(),
                'type' => 'trip_assigned',
                'title' => 'New Trip Assigned',
                'body' => "Trip with {$destinationsCount} deliveries assigned",
                'data' => [
                    'trip_id' => $tripData['trip_id'] ?? fake()->uuid(),
                    'action' => 'open_trip',
                    'total_km' => $tripData['total_km'] ?? fake()->numberBetween(5, 50),
                    'estimated_cost' => $tripData['estimated_cost'] ?? fake()->randomFloat(2, 10, 100),
                ],
            ];
        });
    }

    /**
     * Trip reassigned notification
     */
    public function tripReassigned(User $driver = null): self
    {
        return $this->state(function (array $attributes) use ($driver) {
            return [
                'user_id' => $driver?->id ?? User::factory(),
                'type' => 'trip_reassigned',
                'title' => 'Trip Reassigned',
                'body' => 'Your trip has been reassigned',
                'data' => [
                    'trip_id' => fake()->uuid(),
                    'action' => 'open_trip',
                ],
            ];
        });
    }

    /**
     * Payment notification
     */
    public function paymentReceived(User $driver = null, float $amount = null): self
    {
        $amount ??= fake()->randomFloat(2, 20, 200);
        return $this->state(function (array $attributes) use ($driver, $amount) {
            return [
                'user_id' => $driver?->id ?? User::factory(),
                'type' => 'payment_received',
                'title' => 'Payment Received',
                'body' => "You received a payment of \${$amount}",
                'data' => [
                    'amount' => $amount,
                    'action' => 'open_earnings',
                ],
            ];
        });
    }

    /**
     * Unread notification
     */
    public function unread(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => null,
            ];
        });
    }

    /**
     * Read notification
     */
    public function read(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => now(),
            ];
        });
    }

    /**
     * Failed notification
     */
    public function failed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'failed',
                'sent_at' => null,
            ];
        });
    }

    /**
     * Pending notification
     */
    public function pending(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'sent_at' => null,
            ];
        });
    }
}
