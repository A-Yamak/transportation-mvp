<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_id' => null,
            'phone' => fake()->phoneNumber(),
            'license_number' => strtoupper(fake()->bothify('DL-######')),
            'is_active' => true,
        ];
    }

    /**
     * Driver with assigned vehicle.
     */
    public function withVehicle(?Vehicle $vehicle = null): static
    {
        return $this->state(fn () => [
            'vehicle_id' => $vehicle?->id ?? Vehicle::factory(),
        ]);
    }

    /**
     * Inactive driver.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
