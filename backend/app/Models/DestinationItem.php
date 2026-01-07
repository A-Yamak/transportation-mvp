<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemDeliveryReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DestinationItem Model
 *
 * An individual item within a destination delivery.
 * Tracks quantity ordered vs delivered for partial delivery support.
 *
 * @property string $id UUID
 * @property string $destination_id FK to destinations
 * @property string $order_item_id External item ID from client ERP
 * @property string|null $name Human-readable item name
 * @property float|null $unit_price Price per unit
 * @property int $quantity_ordered Original quantity expected
 * @property int $quantity_delivered Actual quantity delivered
 * @property ItemDeliveryReason|null $delivery_reason Reason for discrepancy
 * @property string|null $notes Additional notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DestinationItem extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'destination_id',
        'order_item_id',
        'name',
        'unit_price',
        'quantity_ordered',
        'quantity_delivered',
        'delivery_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity_ordered' => 'integer',
            'quantity_delivered' => 'integer',
            'delivery_reason' => ItemDeliveryReason::class,
        ];
    }

    /**
     * Get the line total (unit_price * quantity_ordered).
     */
    public function getLineTotalAttribute(): ?float
    {
        if ($this->unit_price === null) {
            return null;
        }
        return round((float) $this->unit_price * $this->quantity_ordered, 2);
    }

    /**
     * Get the delivered total (unit_price * quantity_delivered).
     */
    public function getDeliveredTotalAttribute(): ?float
    {
        if ($this->unit_price === null) {
            return null;
        }
        return round((float) $this->unit_price * $this->quantity_delivered, 2);
    }

    /**
     * Destination this item belongs to.
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * Check if item was fully delivered.
     */
    public function isFullyDelivered(): bool
    {
        return $this->quantity_delivered >= $this->quantity_ordered;
    }

    /**
     * Check if item has a discrepancy.
     */
    public function hasDiscrepancy(): bool
    {
        return $this->quantity_delivered < $this->quantity_ordered;
    }

    /**
     * Get the quantity difference (shortage).
     */
    public function getShortageAttribute(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_delivered);
    }
}
