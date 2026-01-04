# Developer 1: Financial Core (Ledger System)

**Date**: 2026-01-04
**Phase**: 1.9 - Ledger System Implementation
**Estimated Time**: 6-8 hours
**Priority**: CRITICAL (Blocks financial tracking)

---

## 🎯 Mission

Build a complete double-entry accounting system from scratch to track all financial transactions in the transportation business (trip revenue, fuel expenses, driver payments, vehicle maintenance).

---

## 📋 Task Overview

| Task | Files | Tests Required | Time Estimate |
|------|-------|----------------|---------------|
| 1. Create ledger migrations | 3 migrations | - | 45 min |
| 2. Create ledger models | 3 models | - | 60 min |
| 3. Create factories | 3 factories | - | 45 min |
| 4. Create LedgerService | 1 service | Unit tests | 90 min |
| 5. Create ChartOfAccountsSeeder | 1 seeder | - | 30 min |
| 6. Write feature tests | 1 test file | Feature tests | 90 min |
| 7. Integration testing | - | All tests | 30 min |
| **Total** | **13 files** | **90%+ coverage** | **6-8 hours** |

---

## 🗂️ Files You Will Create (Zero Conflicts)

### Migrations (3 files)
```
database/migrations/2026_01_04_100000_create_ledger_accounts_table.php
database/migrations/2026_01_04_100001_create_journal_entries_table.php
database/migrations/2026_01_04_100002_create_journal_entry_items_table.php
```

### Models (3 files)
```
app/Models/LedgerAccount.php
app/Models/JournalEntry.php
app/Models/JournalEntryItem.php
```

### Factories (3 files)
```
database/factories/LedgerAccountFactory.php
database/factories/JournalEntryFactory.php
database/factories/JournalEntryItemFactory.php
```

### Service (1 file)
```
app/Services/Ledger/LedgerService.php
```

### Seeder (1 file)
```
database/seeders/ChartOfAccountsSeeder.php
```

### Tests (2 files)
```
tests/Feature/LedgerSystemTest.php
tests/Unit/Services/Ledger/LedgerServiceTest.php
```

### Files to Edit (1 file - minor)
```
database/seeders/DatabaseSeeder.php (add ChartOfAccountsSeeder call)
```

---

## 📐 Technical Specifications

### Database Schema Requirements

#### 1. ledger_accounts Table

**Purpose**: Chart of Accounts (Assets, Liabilities, Equity, Revenue, Expenses)

**Columns**:
```php
Schema::create('ledger_accounts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('code', 10)->unique(); // e.g., "1100", "4100"
    $table->string('name', 100); // e.g., "Cash", "Delivery Revenue"
    $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
    // OR use: $table->string('account_type'); with LedgerAccountType enum casting in model
    $table->uuid('parent_account_id')->nullable();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('parent_account_id')
          ->references('id')
          ->on('ledger_accounts')
          ->nullOnDelete();

    $table->index(['account_type', 'is_active']);
    $table->index('code');
});
```

**Business Rules**:
- Code must be unique (e.g., 1100 for Cash)
- Account type must match LedgerAccountType enum
- Parent account allows hierarchical structure (1000 → 1100, 1200)
- Only active accounts can be used in journal entries

---

#### 2. journal_entries Table

**Purpose**: Financial transactions (trip revenue, fuel costs, driver payments)

**Columns**:
```php
Schema::create('journal_entries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('entry_number', 20)->unique(); // e.g., "JE-2026-0001"
    $table->date('entry_date');
    $table->text('description'); // e.g., "Trip revenue for delivery #DR-001"
    $table->string('reference_type')->nullable(); // e.g., "App\\Models\\Trip"
    $table->uuid('reference_id')->nullable(); // Trip ID, etc.
    $table->uuid('created_by')->nullable(); // User who created entry
    $table->timestamps();

    $table->foreign('created_by')
          ->references('id')
          ->on('users')
          ->nullOnDelete();

    $table->index('entry_number');
    $table->index('entry_date');
    $table->index(['reference_type', 'reference_id']);
});
```

**Business Rules**:
- Entry number auto-generated (format: JE-YYYY-####)
- Description is mandatory (explain the transaction)
- Reference links to Trip, Business, Driver, etc. (polymorphic-like)
- Each entry must have balanced debit/credit (enforced in service)

---

#### 3. journal_entry_items Table

**Purpose**: Individual debit/credit lines (double-entry bookkeeping)

**Columns**:
```php
Schema::create('journal_entry_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('journal_entry_id');
    $table->uuid('ledger_account_id');
    $table->enum('type', ['debit', 'credit']);
    $table->decimal('amount', 10, 2); // Always positive, type determines debit/credit
    $table->text('memo')->nullable();
    $table->timestamps();

    $table->foreign('journal_entry_id')
          ->references('id')
          ->on('journal_entries')
          ->cascadeOnDelete();

    $table->foreign('ledger_account_id')
          ->references('id')
          ->on('ledger_accounts')
          ->restrictOnDelete(); // Prevent deleting accounts with entries

    $table->index('journal_entry_id');
    $table->index('ledger_account_id');
});
```

**Business Rules**:
- Each entry must have at least 2 items (minimum 1 debit + 1 credit)
- Amount is always positive (type field determines debit/credit)
- Total debits must equal total credits for each journal entry
- Cannot delete ledger account if it has journal entry items

---

### Model Specifications

#### 1. LedgerAccount Model

**File**: `app/Models/LedgerAccount.php`

**Required Properties**:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerAccountType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a ledger account in the chart of accounts.
 *
 * Follows double-entry accounting principles with hierarchical structure.
 */
class LedgerAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'account_type',
        'parent_account_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'account_type' => LedgerAccountType::class,
        'is_active' => 'boolean',
    ];

    // Relationships
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'parent_account_id');
    }

    public function journalEntryItems(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, LedgerAccountType $type)
    {
        return $query->where('account_type', $type);
    }

    // Methods

    /**
     * Get the normal balance type for this account.
     *
     * Assets, Expenses = Debit balance
     * Liabilities, Equity, Revenue = Credit balance
     */
    public function getNormalBalanceAttribute(): string
    {
        return $this->account_type->normalBalance();
    }

    /**
     * Get the full account name with code.
     *
     * Example: "1100 - Cash"
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Check if this account can be deleted.
     *
     * Accounts with journal entry items or child accounts cannot be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->journalEntryItems()->count() === 0
            && $this->childAccounts()->count() === 0;
    }
}
```

---

#### 2. JournalEntry Model

**File**: `app/Models/JournalEntry.php`

**Required Properties**:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a journal entry in the double-entry accounting system.
 *
 * Each entry contains multiple items (debits and credits) that must balance.
 */
class JournalEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'description',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reference model (polymorphic-like relationship).
     *
     * @return Model|null
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            return $this->reference_type::find($this->reference_id);
        }
        return null;
    }

    // Scopes
    public function scopeByDate($query, $startDate, $endDate = null)
    {
        $query->where('entry_date', '>=', $startDate);
        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }
        return $query;
    }

    // Methods

    /**
     * Get total debit amount for this entry.
     */
    public function getTotalDebitAttribute(): float
    {
        return (float) $this->items()
            ->where('type', 'debit')
            ->sum('amount');
    }

    /**
     * Get total credit amount for this entry.
     */
    public function getTotalCreditAttribute(): float
    {
        return (float) $this->items()
            ->where('type', 'credit')
            ->sum('amount');
    }

    /**
     * Check if this entry is balanced (debits = credits).
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01; // Float comparison tolerance
    }

    /**
     * Generate the next entry number.
     *
     * Format: JE-YYYY-####
     */
    public static function generateEntryNumber(): string
    {
        $year = date('Y');
        $prefix = "JE-{$year}-";

        $lastEntry = self::where('entry_number', 'like', "{$prefix}%")
            ->orderBy('entry_number', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->entry_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
```

---

#### 3. JournalEntryItem Model

**File**: `app/Models/JournalEntryItem.php`

**Required Properties**:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a single debit or credit line in a journal entry.
 *
 * Part of double-entry bookkeeping system.
 */
class JournalEntryItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'journal_entry_id',
        'ledger_account_id',
        'type',
        'amount',
        'memo',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    // Methods

    /**
     * Check if this is a debit entry.
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Check if this is a credit entry.
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Get the signed amount (positive for debit, negative for credit).
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isDebit() ? $this->amount : -$this->amount;
    }
}
```

---

### Factory Specifications

#### 1. LedgerAccountFactory

**File**: `database/factories/LedgerAccountFactory.php`

```php
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
            'account_type' => LedgerAccountType::asset,
            'code' => $this->faker->unique()->numberBetween(1000, 1999),
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::liability,
            'code' => $this->faker->unique()->numberBetween(2000, 2999),
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::revenue,
            'code' => $this->faker->unique()->numberBetween(4000, 4999),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => LedgerAccountType::expense,
            'code' => $this->faker->unique()->numberBetween(5000, 5999),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

---

#### 2. JournalEntryFactory

**File**: `database/factories/JournalEntryFactory.php`

```php
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
```

---

#### 3. JournalEntryItemFactory

**File**: `database/factories/JournalEntryItemFactory.php`

```php
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

    public function debit(float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'amount' => $amount ?? $this->faker->randomFloat(2, 10, 1000),
        ]);
    }

    public function credit(float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'amount' => $amount ?? $this->faker->randomFloat(2, 10, 1000),
        ]);
    }
}
```

---

### Service Specification

#### LedgerService

**File**: `app/Services/Ledger/LedgerService.php`

**Required Methods**:

```php
<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles double-entry accounting operations.
 *
 * All financial transactions must use this service to ensure proper
 * double-entry bookkeeping (debits = credits).
 */
class LedgerService
{
    /**
     * Create a journal entry with balanced debits and credits.
     *
     * @param array $data [
     *   'entry_date' => '2026-01-04',
     *   'description' => 'Trip revenue',
     *   'reference_type' => 'App\Models\Trip',
     *   'reference_id' => 'uuid',
     *   'created_by' => 'user-uuid',
     *   'items' => [
     *     ['account_code' => '1200', 'type' => 'debit', 'amount' => 150.00, 'memo' => '...'],
     *     ['account_code' => '4100', 'type' => 'credit', 'amount' => 150.00, 'memo' => '...'],
     *   ]
     * ]
     * @return JournalEntry
     * @throws InvalidArgumentException if debits != credits
     */
    public function createJournalEntry(array $data): JournalEntry
    {
        // Validate balanced entry
        $totalDebit = collect($data['items'])->where('type', 'debit')->sum('amount');
        $totalCredit = collect($data['items'])->where('type', 'credit')->sum('amount');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(
                "Journal entry must be balanced. Debit: {$totalDebit}, Credit: {$totalCredit}"
            );
        }

        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $account = LedgerAccount::where('code', $item['account_code'])->firstOrFail();

                $entry->items()->create([
                    'ledger_account_id' => $account->id,
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            return $entry->load('items.ledgerAccount');
        });
    }

    /**
     * Get the balance of a specific ledger account.
     *
     * @param string $accountCode Account code (e.g., "1100" for Cash)
     * @param string|null $asOfDate Optional date to calculate balance as of that date
     * @return float Balance (positive for debit balance accounts, negative for credit balance)
     */
    public function getAccountBalance(string $accountCode, ?string $asOfDate = null): float
    {
        $account = LedgerAccount::where('code', $accountCode)->firstOrFail();

        $query = $account->journalEntryItems()
            ->join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id');

        if ($asOfDate) {
            $query->where('journal_entries.entry_date', '<=', $asOfDate);
        }

        $debits = (float) $query->clone()->where('journal_entry_items.type', 'debit')->sum('journal_entry_items.amount');
        $credits = (float) $query->clone()->where('journal_entry_items.type', 'credit')->sum('journal_entry_items.amount');

        // Return based on account's normal balance
        $balance = $debits - $credits;

        return $account->account_type->normalBalance() === 'debit' ? $balance : -$balance;
    }

    /**
     * Get trial balance (all accounts with their balances).
     *
     * @param string|null $asOfDate Optional date
     * @return array [
     *   ['code' => '1100', 'name' => 'Cash', 'debit' => 5000.00, 'credit' => 0],
     *   ['code' => '4100', 'name' => 'Revenue', 'debit' => 0, 'credit' => 5000.00],
     *   'totals' => ['debit' => 5000.00, 'credit' => 5000.00]
     * ]
     */
    public function getTrialBalance(?string $asOfDate = null): array
    {
        $accounts = LedgerAccount::active()->get();
        $balances = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->code, $asOfDate);

            $debit = $balance >= 0 ? $balance : 0;
            $credit = $balance < 0 ? abs($balance) : 0;

            $balances[] = [
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type->value,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'accounts' => $balances,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
            ],
        ];
    }

    /**
     * Record trip revenue (when trip is completed and billed).
     *
     * DEBIT  1200 Accounts Receivable (Business)  $amount
     * CREDIT 4100 Delivery Revenue                $amount
     *
     * @param Trip $trip
     * @param float $amount Revenue amount
     * @return JournalEntry
     */
    public function recordTripRevenue(Trip $trip, float $amount): JournalEntry
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => "Trip revenue for delivery request {$trip->deliveryRequest->id}",
            'reference_type' => Trip::class,
            'reference_id' => $trip->id,
            'items' => [
                [
                    'account_code' => '1200', // Accounts Receivable
                    'type' => 'debit',
                    'amount' => $amount,
                    'memo' => "Trip {$trip->id} - Business {$trip->deliveryRequest->business->name}",
                ],
                [
                    'account_code' => '4100', // Delivery Revenue
                    'type' => 'credit',
                    'amount' => $amount,
                    'memo' => "Trip {$trip->id}",
                ],
            ],
        ]);
    }

    /**
     * Record fuel expense.
     *
     * DEBIT  5100 Fuel Expense  $amount
     * CREDIT 1100 Cash          $amount
     *
     * @param float $amount Fuel cost
     * @param string $description Fuel purchase description
     * @return JournalEntry
     */
    public function recordFuelExpense(float $amount, string $description): JournalEntry
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'items' => [
                [
                    'account_code' => '5100', // Fuel Expense
                    'type' => 'debit',
                    'amount' => $amount,
                    'memo' => $description,
                ],
                [
                    'account_code' => '1100', // Cash
                    'type' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Fuel payment',
                ],
            ],
        ]);
    }

    /**
     * Record driver payment.
     *
     * DEBIT  5200 Driver Payments  $amount
     * CREDIT 1100 Cash             $amount
     *
     * @param string $driverId Driver UUID
     * @param float $amount Payment amount
     * @param string $description Payment description
     * @return JournalEntry
     */
    public function recordDriverPayment(string $driverId, float $amount, string $description): JournalEntry
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'items' => [
                [
                    'account_code' => '5200', // Driver Payments
                    'type' => 'debit',
                    'amount' => $amount,
                    'memo' => "Driver {$driverId} - {$description}",
                ],
                [
                    'account_code' => '1100', // Cash
                    'type' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Driver payment',
                ],
            ],
        ]);
    }
}
```

---

### Seeder Specification

#### ChartOfAccountsSeeder

**File**: `database/seeders/ChartOfAccountsSeeder.php`

**Required Accounts**:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * Seeds the Chart of Accounts for the transportation business.
 *
 * Follows standard accounting structure:
 * 1000 - Assets
 * 2000 - Liabilities
 * 3000 - Equity
 * 4000 - Revenue
 * 5000 - Expenses
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1000-1999)
            [
                'code' => '1000',
                'name' => 'Assets',
                'account_type' => LedgerAccountType::asset,
                'description' => 'All company assets',
            ],
            [
                'code' => '1100',
                'name' => 'Cash',
                'account_type' => LedgerAccountType::asset,
                'parent_code' => '1000',
                'description' => 'Cash on hand and in bank',
            ],
            [
                'code' => '1200',
                'name' => 'Accounts Receivable',
                'account_type' => LedgerAccountType::asset,
                'parent_code' => '1000',
                'description' => 'Money owed by businesses for completed deliveries',
            ],
            [
                'code' => '1400',
                'name' => 'Vehicles',
                'account_type' => LedgerAccountType::asset,
                'parent_code' => '1000',
                'description' => 'Company vehicles (VW Caddy, etc.)',
            ],

            // Liabilities (2000-2999)
            [
                'code' => '2000',
                'name' => 'Liabilities',
                'account_type' => LedgerAccountType::liability,
                'description' => 'All company liabilities',
            ],
            [
                'code' => '2100',
                'name' => 'Accounts Payable',
                'account_type' => LedgerAccountType::liability,
                'parent_code' => '2000',
                'description' => 'Money owed to suppliers and vendors',
            ],

            // Equity (3000-3999)
            [
                'code' => '3000',
                'name' => 'Equity',
                'account_type' => LedgerAccountType::equity,
                'description' => 'Owner equity',
            ],

            // Revenue (4000-4999)
            [
                'code' => '4000',
                'name' => 'Revenue',
                'account_type' => LedgerAccountType::revenue,
                'description' => 'All company revenue',
            ],
            [
                'code' => '4100',
                'name' => 'Delivery Revenue',
                'account_type' => LedgerAccountType::revenue,
                'parent_code' => '4000',
                'description' => 'Revenue from delivery services',
            ],

            // Expenses (5000-5999)
            [
                'code' => '5000',
                'name' => 'Expenses',
                'account_type' => LedgerAccountType::expense,
                'description' => 'All company expenses',
            ],
            [
                'code' => '5100',
                'name' => 'Fuel Expense',
                'account_type' => LedgerAccountType::expense,
                'parent_code' => '5000',
                'description' => 'Fuel costs for vehicles',
            ],
            [
                'code' => '5200',
                'name' => 'Driver Payments',
                'account_type' => LedgerAccountType::expense,
                'parent_code' => '5000',
                'description' => 'Payments to drivers',
            ],
            [
                'code' => '5300',
                'name' => 'Vehicle Maintenance',
                'account_type' => LedgerAccountType::expense,
                'parent_code' => '5000',
                'description' => 'Vehicle repairs and maintenance',
            ],
        ];

        foreach ($accounts as $accountData) {
            $parentCode = $accountData['parent_code'] ?? null;
            unset($accountData['parent_code']);

            $account = LedgerAccount::create($accountData);

            if ($parentCode) {
                $parent = LedgerAccount::where('code', $parentCode)->first();
                if ($parent) {
                    $account->update(['parent_account_id' => $parent->id]);
                }
            }
        }

        $this->command->info('Chart of Accounts created successfully.');
    }
}
```

**Then update** `database/seeders/DatabaseSeeder.php`:
```php
public function run(): void
{
    $this->call([
        ChartOfAccountsSeeder::class, // Add this line
        // ... other seeders
    ]);
}
```

---

## 🧪 Testing Requirements

### Feature Test

**File**: `tests/Feature/LedgerSystemTest.php`

**Required Test Cases**:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LedgerAccountType;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSystemTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed chart of accounts
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $this->ledgerService = app(LedgerService::class);
    }

    public function test_chart_of_accounts_seeder_creates_all_accounts(): void
    {
        $this->assertDatabaseCount('ledger_accounts', 13); // Total accounts from seeder

        $this->assertDatabaseHas('ledger_accounts', [
            'code' => '1100',
            'name' => 'Cash',
        ]);

        $this->assertDatabaseHas('ledger_accounts', [
            'code' => '4100',
            'name' => 'Delivery Revenue',
        ]);
    }

    public function test_can_create_balanced_journal_entry(): void
    {
        $entry = $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Test entry',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 100.00],
            ],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertEquals(100.00, $entry->total_debit);
        $this->assertEquals(100.00, $entry->total_credit);
    }

    public function test_cannot_create_unbalanced_journal_entry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal entry must be balanced');

        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Unbalanced entry',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 50.00], // Not balanced!
            ],
        ]);
    }

    public function test_can_record_trip_revenue(): void
    {
        $business = Business::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->for($business)->create();
        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($driver)
            ->for($vehicle)
            ->create();

        $entry = $this->ledgerService->recordTripRevenue($trip, 150.00);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());

        // Check Accounts Receivable debited
        $arItem = $entry->items()->whereHas('ledgerAccount', function ($q) {
            $q->where('code', '1200');
        })->first();
        $this->assertEquals('debit', $arItem->type);
        $this->assertEquals(150.00, $arItem->amount);

        // Check Revenue credited
        $revenueItem = $entry->items()->whereHas('ledgerAccount', function ($q) {
            $q->where('code', '4100');
        })->first();
        $this->assertEquals('credit', $revenueItem->type);
        $this->assertEquals(150.00, $revenueItem->amount);
    }

    public function test_can_record_fuel_expense(): void
    {
        $entry = $this->ledgerService->recordFuelExpense(50.00, 'Fuel purchase at station XYZ');

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertEquals(50.00, $entry->total_debit);
        $this->assertEquals(50.00, $entry->total_credit);
    }

    public function test_can_record_driver_payment(): void
    {
        $driver = Driver::factory()->create();

        $entry = $this->ledgerService->recordDriverPayment(
            $driver->id,
            100.00,
            'Weekly payment'
        );

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
    }

    public function test_can_get_account_balance(): void
    {
        // Create some transactions
        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Initial cash',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 1000.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 1000.00],
            ],
        ]);

        $this->ledgerService->recordFuelExpense(50.00, 'Fuel');

        $cashBalance = $this->ledgerService->getAccountBalance('1100');

        // Cash should be 1000 (initial) - 50 (fuel) = 950
        $this->assertEquals(950.00, $cashBalance);
    }

    public function test_can_get_trial_balance(): void
    {
        // Create some transactions
        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Initial cash',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 1000.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 1000.00],
            ],
        ]);

        $trialBalance = $this->ledgerService->getTrialBalance();

        $this->assertIsArray($trialBalance);
        $this->assertArrayHasKey('accounts', $trialBalance);
        $this->assertArrayHasKey('totals', $trialBalance);

        // Totals must be balanced
        $this->assertEquals(
            $trialBalance['totals']['debit'],
            $trialBalance['totals']['credit']
        );
    }

    public function test_ledger_account_has_correct_normal_balance(): void
    {
        $cash = LedgerAccount::where('code', '1100')->first();
        $this->assertEquals('debit', $cash->normal_balance);

        $revenue = LedgerAccount::where('code', '4100')->first();
        $this->assertEquals('credit', $revenue->normal_balance);
    }

    public function test_journal_entry_generates_unique_entry_numbers(): void
    {
        $entry1 = $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Entry 1',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 100.00],
            ],
        ]);

        $entry2 = $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Entry 2',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 200.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 200.00],
            ],
        ]);

        $this->assertNotEquals($entry1->entry_number, $entry2->entry_number);
        $this->assertStringStartsWith('JE-2026-', $entry1->entry_number);
        $this->assertStringStartsWith('JE-2026-', $entry2->entry_number);
    }

    public function test_cannot_delete_account_with_journal_entries(): void
    {
        $cash = LedgerAccount::where('code', '1100')->first();

        // Create entry using cash account
        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Test',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 100.00],
            ],
        ]);

        $this->assertFalse($cash->canBeDeleted());
    }
}
```

---

### Unit Test

**File**: `tests/Unit/Services/Ledger/LedgerServiceTest.php`

**Required Test Cases**:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ledger;

use App\Models\LedgerAccount;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
        $this->service = app(LedgerService::class);
    }

    public function test_validates_balanced_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Unbalanced',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 99.00],
            ],
        ]);
    }

    public function test_calculates_account_balance_correctly(): void
    {
        // Initial cash: 1000 debit
        $this->service->createJournalEntry([
            'entry_date' => '2026-01-01',
            'description' => 'Initial',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 1000.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 1000.00],
            ],
        ]);

        // Fuel expense: 50 credit to cash
        $this->service->createJournalEntry([
            'entry_date' => '2026-01-02',
            'description' => 'Fuel',
            'items' => [
                ['account_code' => '5100', 'type' => 'debit', 'amount' => 50.00],
                ['account_code' => '1100', 'type' => 'credit', 'amount' => 50.00],
            ],
        ]);

        $balance = $this->service->getAccountBalance('1100');
        $this->assertEquals(950.00, $balance);
    }

    public function test_trial_balance_is_always_balanced(): void
    {
        // Create multiple entries
        $this->service->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Entry 1',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 500.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 500.00],
            ],
        ]);

        $this->service->recordFuelExpense(50.00, 'Fuel');

        $trialBalance = $this->service->getTrialBalance();

        $this->assertEquals(
            $trialBalance['totals']['debit'],
            $trialBalance['totals']['credit']
        );
    }
}
```

---

## ✅ Completion Checklist

### Phase 1: Migrations & Models (2 hours)
- [ ] Create `2026_01_04_100000_create_ledger_accounts_table.php`
- [ ] Create `2026_01_04_100001_create_journal_entries_table.php`
- [ ] Create `2026_01_04_100002_create_journal_entry_items_table.php`
- [ ] Run migrations (`php artisan migrate`)
- [ ] Create `LedgerAccount` model with relationships
- [ ] Create `JournalEntry` model with relationships
- [ ] Create `JournalEntryItem` model with relationships
- [ ] Create all 3 factories

### Phase 2: Service & Seeder (2 hours)
- [ ] Create `app/Services/Ledger/LedgerService.php`
- [ ] Implement `createJournalEntry()` method
- [ ] Implement `getAccountBalance()` method
- [ ] Implement `getTrialBalance()` method
- [ ] Implement `recordTripRevenue()` method
- [ ] Implement `recordFuelExpense()` method
- [ ] Implement `recordDriverPayment()` method
- [ ] Create `ChartOfAccountsSeeder.php`
- [ ] Update `DatabaseSeeder.php`
- [ ] Test seeder runs successfully

### Phase 3: Testing (3 hours)
- [ ] Create `tests/Feature/LedgerSystemTest.php`
- [ ] Write test: chart of accounts seeder works
- [ ] Write test: can create balanced journal entry
- [ ] Write test: cannot create unbalanced entry
- [ ] Write test: can record trip revenue
- [ ] Write test: can record fuel expense
- [ ] Write test: can record driver payment
- [ ] Write test: can get account balance
- [ ] Write test: can get trial balance
- [ ] Write test: entry numbers are unique
- [ ] Create `tests/Unit/Services/Ledger/LedgerServiceTest.php`
- [ ] Write unit tests for service methods
- [ ] Run `php artisan test` - all tests pass

### Phase 4: Quality Assurance (30 min)
- [ ] PSR-12 compliance (`./vendor/bin/phpcs`)
- [ ] PHPDoc on all public methods
- [ ] Type hints on all parameters and return types
- [ ] No `dd()`, `var_dump()`, or debug statements
- [ ] Code coverage >80% (`php artisan test --coverage`)

---

## 🚫 What NOT to Touch

**Do NOT edit these files** (to avoid merge conflicts):
- ❌ Any existing models (Business, Vehicle, Driver, etc.)
- ❌ Existing migrations
- ❌ Routes files
- ❌ Controllers
- ❌ Filament resources
- ❌ Config files (except for adding service binding in AppServiceProvider if needed)

---

## 🔧 Commands You'll Use

```bash
# Create migrations
php artisan make:migration create_ledger_accounts_table
php artisan make:migration create_journal_entries_table
php artisan make:migration create_journal_entry_items_table

# Create models with factories
php artisan make:model LedgerAccount -f
php artisan make:model JournalEntry -f
php artisan make:model JournalEntryItem -f

# Create seeder
php artisan make:seeder ChartOfAccountsSeeder

# Create test files
php artisan make:test LedgerSystemTest
php artisan make:test Services/Ledger/LedgerServiceTest --unit

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed --class=ChartOfAccountsSeeder

# Run tests
php artisan test
php artisan test --filter=LedgerSystemTest
php artisan test --coverage

# Reset database
php artisan migrate:fresh --seed
```

---

## 📝 Notes

1. **TDD Approach**: Write tests BEFORE implementing service methods
2. **Transactions**: Use DB::transaction() for journal entry creation
3. **Validation**: Always validate debits = credits
4. **Float Precision**: Use `abs($debit - $credit) < 0.01` for float comparison
5. **Entry Numbers**: Auto-generate unique entry numbers (JE-YYYY-####)
6. **Account Codes**: Use exact codes from seeder (1100, 1200, 4100, etc.)
7. **Dependencies**: LedgerService will be used by CostCalculator (Developer 2) in Phase 3

---

## 🎯 Success Criteria

By end of day, you should have:
- ✅ 3 migrations run successfully
- ✅ 3 models with full relationships
- ✅ 3 factories
- ✅ 1 service with 6+ methods
- ✅ 1 seeder creating 13 accounts
- ✅ All tests passing (green)
- ✅ Code coverage >80%
- ✅ PSR-12 compliant
- ✅ Full PHPDoc coverage

---

**Good luck! You're building the financial backbone of the system.**
