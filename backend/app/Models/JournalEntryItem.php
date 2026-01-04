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
 *
 * @property string $id UUID
 * @property string $journal_entry_id Journal entry UUID
 * @property string $ledger_account_id Ledger account UUID
 * @property string $type Type: 'debit' or 'credit'
 * @property float $amount Amount (always positive)
 * @property string|null $memo Optional memo/note
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Journal entry this item belongs to.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Ledger account this item affects.
     */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

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
