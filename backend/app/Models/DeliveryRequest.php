<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * DeliveryRequest Model
 *
 * A batch of destinations to deliver from one business client.
 * Route is optimized via Google Maps when request is created.
 *
 * @property string $id UUID
 * @property string $business_id FK to businesses
 * @property DeliveryRequestStatus $status Current status
 * @property float|null $total_km Total kilometers (from route optimization)
 * @property float|null $estimated_cost Estimated cost
 * @property float|null $actual_km Actual kilometers (from GPS tracking)
 * @property float|null $actual_cost Actual cost
 * @property array|null $optimized_route Polyline from Google Maps
 * @property string|null $notes Additional notes
 * @property \Carbon\Carbon $requested_at When request was made
 * @property \Carbon\Carbon|null $completed_at When completed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DeliveryRequest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'business_id',
        'status',
        'total_km',
        'estimated_cost',
        'actual_km',
        'actual_cost',
        'optimized_route',
        'notes',
        'requested_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryRequestStatus::class,
            'total_km' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'actual_km' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'optimized_route' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Business that made this request.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Destinations within this delivery request.
     */
    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class)->orderBy('sequence_order');
    }

    /**
     * Trip assigned to fulfill this request.
     */
    public function trip(): HasOne
    {
        return $this->hasOne(Trip::class);
    }

    /**
     * Get completed destinations count.
     */
    public function getCompletedCountAttribute(): int
    {
        return $this->destinations()->where('status', 'completed')->count();
    }

    /**
     * Get total destinations count.
     */
    public function getTotalDestinationsAttribute(): int
    {
        return $this->destinations()->count();
    }

    /**
     * Check if all destinations are completed.
     */
    public function isFullyCompleted(): bool
    {
        return $this->destinations()
            ->whereNotIn('status', ['completed', 'failed'])
            ->doesntExist();
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(float $actualKm): void
    {
        $pricingTier = PricingTier::getCurrentForBusinessType($this->business->business_type);
        $actualCost = $pricingTier?->calculateCost($actualKm) ?? 0;

        $this->update([
            'status' => DeliveryRequestStatus::Completed,
            'actual_km' => $actualKm,
            'actual_cost' => $actualCost,
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', DeliveryRequestStatus::Pending);
    }

    /**
     * Scope for in-progress requests.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', DeliveryRequestStatus::InProgress);
    }
}
