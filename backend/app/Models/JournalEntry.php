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
 *
 * @property string $id UUID
 * @property string $entry_number Unique entry number (e.g., "JE-2026-0001")
 * @property \Carbon\Carbon $entry_date Date of the entry
 * @property string $description Entry description
 * @property string|null $reference_type Referenced model class
 * @property string|null $reference_id Referenced model UUID
 * @property int|null $created_by User ID who created the entry
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    /**
     * Journal entry items (debits and credits).
     */
    public function items(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    /**
     * User who created this entry.
     */
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

    /**
     * Scope to filter by date range.
     */
    public function scopeByDate($query, $startDate, $endDate = null)
    {
        $query->where('entry_date', '>=', $startDate);
        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        return $query;
    }

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
        return abs($this->total_debit - $this->total_credit) < 0.01;
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

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
