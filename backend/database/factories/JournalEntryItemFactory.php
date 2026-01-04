<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\JournalEntryItem;
use App\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryItemFactory extends Factory
{
    protected $model = JournalEntryItem::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'ledger_account_id' => LedgerAccount::factory(),
            'type' => $this->faker->randomElement(['debit', 'credit']),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'memo' => $this->faker->optional()->sentence(),
        ];
    }

    public function debit(?float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'amount' => $amount ?? $this->faker->randomFloat(2, 10, 1000),
        ]);
    }

    public function credit(?float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'amount' => $amount ?? $this->faker->randomFloat(2, 10, 1000),
        ]);
    }
}
