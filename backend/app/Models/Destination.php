<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DestinationStatus;
use App\Enums\FailureReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Destination Model
 *
 * An individual stop within a delivery request.
 *
 * @property string $id UUID
 * @property string $delivery_request_id FK to delivery_requests
 * @property string $external_id External ID from client ERP (e.g., "order-123")
 * @property string $address Delivery address
 * @property float $lat Latitude
 * @property float $lng Longitude
 * @property string|null $contact_name Customer contact name
 * @property string|null $contact_phone Customer contact phone
 * @property int $sequence_order Optimized sequence (1, 2, 3...)
 * @property DestinationStatus $status Current status
 * @property string|null $notes Additional notes
 * @property float|null $amount_to_collect Total cash to collect from customer
 * @property float|null $amount_collected Actual amount collected
 * @property string|null $recipient_name Name of person who received
 * @property FailureReason|null $failure_reason Reason for failure
 * @property string|null $failure_notes Additional failure notes
 * @property \Carbon\Carbon|null $arrived_at When driver arrived
 * @property \Carbon\Carbon|null $completed_at When delivery completed
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Destination extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'delivery_request_id',
        'external_id',
        'address',
        'lat',
        'lng',
        'contact_name',
        'contact_phone',
        'sequence_order',
        'status',
        'notes',
        'amount_to_collect',
        'amount_collected',
        'recipient_name',
        'failure_reason',
        'failure_notes',
        'arrived_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'sequence_order' => 'integer',
            'status' => DestinationStatus::class,
            'amount_to_collect' => 'decimal:2',
            'amount_collected' => 'decimal:2',
            'failure_reason' => FailureReason::class,
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Calculate total from items if items exist.
     */
    public function calculateTotalFromItems(): ?float
    {
        if (!$this->relationLoaded('items') && !$this->items()->exists()) {
            return null;
        }

        $total = $this->items->sum(function ($item) {
            return $item->line_total ?? 0;
        });

        return $total > 0 ? round($total, 2) : null;
    }

    /**
     * Delivery request this destination belongs to.
     */
    public function deliveryRequest(): BelongsTo
    {
        return $this->belongsTo(DeliveryRequest::class);
    }

    /**
     * Items within this destination (for partial delivery tracking).
     */
    public function items(): HasMany
    {
        return $this->hasMany(DestinationItem::class);
    }

    /**
     * Check if this destination has items tracked.
     */
    public function hasItemTracking(): bool
    {
        return $this->items()->exists();
    }

    /**
     * Check if all items were fully delivered.
     */
    public function allItemsFullyDelivered(): bool
    {
        if (! $this->hasItemTracking()) {
            return true; // No items = assume full delivery
        }

        return $this->items()
            ->whereColumn('quantity_delivered', '<', 'quantity_ordered')
            ->doesntExist();
    }

    /**
     * Get Google Maps navigation URL.
     */
    public function getNavigationUrlAttribute(): string
    {
        return "https://www.google.com/maps/dir/?api=1&destination={$this->lat},{$this->lng}&travelmode=driving";
    }

    /**
     * Mark as arrived.
     */
    public function markArrived(): void
    {
        $this->update([
            'status' => DestinationStatus::Arrived,
            'arrived_at' => now(),
        ]);
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(?string $recipientName = null, ?string $notes = null): void
    {
        $this->update([
            'status' => DestinationStatus::Completed,
            'recipient_name' => $recipientName,
            'notes' => $notes ?? $this->notes,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markFailed(FailureReason $reason, ?string $notes = null): void
    {
        $this->update([
            'status' => DestinationStatus::Failed,
            'failure_reason' => $reason,
            'failure_notes' => $notes,
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope for pending destinations.
     */
    public function scopePending($query)
    {
        return $query->where('status', DestinationStatus::Pending);
    }

    /**
     * Scope for completed destinations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', DestinationStatus::Completed);
    }
}
