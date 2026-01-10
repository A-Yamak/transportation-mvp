<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShortageReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Note: driver() relationship references Driver model

/**
 * PaymentCollection Model
 *
 * Tracks payment collection details for each destination delivery.
 * Records amount expected vs collected, payment method, and any shortages.
 *
 * @property string $id UUID
 * @property string $destination_id FK to destinations
 * @property string $trip_id FK to trips
 * @property string $driver_id FK to users (driver collecting payment)
 * @property decimal $amount_expected Expected cash amount from order
 * @property decimal $amount_collected Actual amount collected
 * @property string $payment_method "cash", "cliq_now", "cliq_later", or "mixed"
 * @property string|null $cliq_reference CliQ transaction ID (if CliQ payment)
 * @property string $payment_status "pending", "collected", "partial", or "failed"
 * @property string|null $shortage_reason Reason for partial payment (if shortage)
 * @property string|null $notes Optional notes about the payment
 * @property \Carbon\Carbon $collected_at Timestamp when collected
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaymentCollection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'destination_id',
        'trip_id',
        'driver_id',
        'amount_expected',
        'amount_collected',
        'payment_method',
        'cliq_reference',
        'payment_status',
        'shortage_reason',
        'notes',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_expected' => 'decimal:2',
            'amount_collected' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'shortage_reason' => ShortageReason::class,
            'collected_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    // ========== SCOPES ==========

    public function scopeCollected($query)
    {
        return $query->where('payment_status', 'collected');
    }

    public function scopePartial($query)
    {
        return $query->where('payment_status', 'partial');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('payment_status', 'failed');
    }

    public function scopeForPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeWithShortage($query)
    {
        return $query->whereColumn('amount_collected', '<', 'amount_expected');
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Check if payment has shortage (collected < expected).
     */
    public function hasShortage(): bool
    {
        return $this->amount_collected < $this->amount_expected;
    }

    /**
     * Get shortage amount (0 if fully collected).
     */
    public function getShortageAmountAttribute(): float
    {
        return max(0, $this->amount_expected - $this->amount_collected);
    }

    /**
     * Get shortage percentage relative to expected amount.
     */
    public function getShortagePercentageAttribute(): float
    {
        if ($this->amount_expected == 0) {
            return 0;
        }

        return round(($this->shortage_amount / $this->amount_expected) * 100, 2);
    }

    /**
     * Check if payment was collected fully.
     */
    public function isFullyCollected(): bool
    {
        return $this->amount_collected >= $this->amount_expected;
    }

    /**
     * Check if payment was collected partially.
     */
    public function isPartial(): bool
    {
        return $this->amount_collected > 0 && !$this->isFullyCollected();
    }

    /**
     * Check if payment was via CliQ (either now or later).
     */
    public function isCliQPayment(): bool
    {
        return in_array($this->payment_method, [PaymentMethod::CliqNow, PaymentMethod::CliqLater]);
    }

    /**
     * Check if payment was cash.
     */
    public function isCashPayment(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }
}
