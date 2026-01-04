<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $makes = [
            'Volkswagen' => ['Caddy', 'Transporter', 'Crafter'],
            'Mercedes-Benz' => ['Sprinter', 'Vito', 'Citan'],
            'Ford' => ['Transit', 'Transit Connect', 'Ranger'],
            'Toyota' => ['Hiace', 'Hilux', 'Land Cruiser'],
            'Nissan' => ['NV200', 'Navara', 'Patrol'],
        ];

        $make = fake()->randomElement(array_keys($makes));
        $model = fake()->randomElement($makes[$make]);

        return [
            'make' => $make,
            'model' => $model,
            'year' => fake()->numberBetween(2015, 2024),
            'license_plate' => strtoupper(fake()->bothify('??-###-??')),
            'total_km_driven' => fake()->randomFloat(2, 0, 150000),
            'monthly_km_app' => fake()->randomFloat(2, 0, 3000),
            'acquisition_date' => fake()->optional(0.8)->dateTimeBetween('-5 years', 'now'),
            'is_active' => true,
        ];
    }

    /**
     * The MVP vehicle (VW Caddy 2019).
     */
    public function mvpVehicle(): static
    {
        return $this->state(fn () => [
            'make' => 'Volkswagen',
            'model' => 'Caddy',
            'year' => 2019,
            'license_plate' => 'JO-123-VW',
            'total_km_driven' => 45000,
            'monthly_km_app' => 1200,
            'acquisition_date' => '2019-06-15',
        ]);
    }

    /**
     * Inactive vehicle.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * New vehicle with zero kilometers.
     */
    public function new(): static
    {
        return $this->state(fn () => [
            'total_km_driven' => 0,
            'monthly_km_app' => 0,
            'acquisition_date' => now(),
        ]);
    }
}
