<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function forTrip(string $tripId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => 'App\\Models\\Trip',
            'reference_id' => $tripId,
            'description' => "Trip revenue for delivery {$tripId}",
        ]);
    }
}
