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
 *
 * @property string $id UUID
 * @property string $code Account code (e.g., "1100", "4100")
 * @property string $name Account name
 * @property LedgerAccountType $account_type Type of account
 * @property string|null $parent_account_id Parent account UUID
 * @property string|null $description Account description
 * @property bool $is_active Whether account is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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

    protected function casts(): array
    {
        return [
            'account_type' => LedgerAccountType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent account in the account hierarchy.
     */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'parent_account_id');
    }

    /**
     * Child accounts under this account.
     */
    public function childAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'parent_account_id');
    }

    /**
     * Journal entry items using this account.
     */
    public function journalEntryItems(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    /**
     * Scope to only active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by account type.
     */
    public function scopeByType($query, LedgerAccountType $type)
    {
        return $query->where('account_type', $type);
    }

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
