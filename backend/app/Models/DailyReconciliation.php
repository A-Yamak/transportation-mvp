<?php

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DailyReconciliation Model
 *
 * Tracks daily reconciliation for a driver at end-of-day.
 * Summarizes all trips, deliveries, payments, and KM for the day.
 * Includes per-shop breakdown of amounts collected.
 *
 * @property string $id UUID
 * @property string $driver_id FK to users (driver)
 * @property string $business_id FK to businesses
 * @property \Carbon\Carbon $reconciliation_date Date of reconciliation
 * @property decimal $total_expected Total expected cash for the day
 * @property decimal $total_collected Total cash actually collected
 * @property decimal $total_cash Amount collected in cash
 * @property decimal $total_cliq Amount collected via CliQ
 * @property int $trips_completed Number of trips completed
 * @property int $deliveries_completed Number of deliveries completed
 * @property decimal $total_km_driven Total KM driven during day
 * @property string $status "pending", "submitted", "acknowledged", or "disputed"
 * @property array $shop_breakdown Array of {shop_id, amount_collected, method} per shop
 * @property \Carbon\Carbon|null $submitted_at Timestamp when submitted to Melo ERP
 * @property \Carbon\Carbon|null $acknowledged_at Timestamp when acknowledged by admin
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DailyReconciliation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'driver_id',
        'business_id',
        'reconciliation_date',
        'total_expected',
        'total_collected',
        'total_cash',
        'total_cliq',
        'trips_completed',
        'deliveries_completed',
        'total_km_driven',
        'status',
        'shop_breakdown',
        'submitted_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'total_expected' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'total_cash' => 'decimal:2',
            'total_cliq' => 'decimal:2',
            'total_km_driven' => 'decimal:2',
            'status' => ReconciliationStatus::class,
            'shop_breakdown' => 'array',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', 'disputed');
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('reconciliation_date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('reconciliation_date', [$startDate, $endDate]);
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Get collection rate as percentage (collected / expected).
     */
    public function getCollectionRateAttribute(): float
    {
        if ($this->total_expected == 0) {
            return 100;
        }

        return round(($this->total_collected / $this->total_expected) * 100, 2);
    }

    /**
     * Get shortage amount (expected - collected).
     */
    public function getShortageAmountAttribute(): float
    {
        return max(0, $this->total_expected - $this->total_collected);
    }

    /**
     * Get overage amount (collected - expected), if any.
     */
    public function getOverageAmountAttribute(): float
    {
        return max(0, $this->total_collected - $this->total_expected);
    }

    /**
     * Check if there's a shortage.
     */
    public function hasShortage(): bool
    {
        return $this->total_collected < $this->total_expected;
    }

    /**
     * Check if there's an overage.
     */
    public function hasOverage(): bool
    {
        return $this->total_collected > $this->total_expected;
    }

    /**
     * Check if fully collected.
     */
    public function isFullyCollected(): bool
    {
        return $this->total_collected >= $this->total_expected;
    }

    /**
     * Get percentage collected via cash.
     */
    public function getCashPercentageAttribute(): float
    {
        if ($this->total_collected == 0) {
            return 0;
        }

        return round(($this->total_cash / $this->total_collected) * 100, 2);
    }

    /**
     * Get percentage collected via CliQ.
     */
    public function getCliqPercentageAttribute(): float
    {
        if ($this->total_collected == 0) {
            return 0;
        }

        return round(($this->total_cliq / $this->total_collected) * 100, 2);
    }

    /**
     * Mark reconciliation as submitted.
     */
    public function markAsSubmitted(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Mark reconciliation as acknowledged.
     */
    public function markAsAcknowledged(): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Mark reconciliation as disputed.
     */
    public function markAsDisputed(): void
    {
        $this->update([
            'status' => 'disputed',
        ]);
    }
}
