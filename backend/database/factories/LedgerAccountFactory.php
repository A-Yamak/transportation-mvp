<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('####'),
            'name' => $this->faker->words(2, true),
            'account_type' => $this->faker->randomElement(LedgerAccountType::cases()),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::Asset,
            'code' => (string) $this->faker->unique()->numberBetween(1000, 1999),
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::Liability,
            'code' => (string) $this->faker->unique()->numberBetween(2000, 2999),
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::Revenue,
            'code' => (string) $this->faker->unique()->numberBetween(4000, 4999),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::Expense,
            'code' => (string) $this->faker->unique()->numberBetween(5000, 5999),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
