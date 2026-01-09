<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WasteCollectionItem Model
 *
 * Individual item within a waste collection.
 * Can be linked to original delivery item for traceability.
 * Uses generated column for pieces_sold calculation.
 *
 * @property string $id UUID
 * @property string $waste_collection_id FK to waste_collections
 * @property string|null $destination_item_id FK to destination_items (original delivery)
 * @property string $order_item_id External item ID from ERP
 * @property string|null $product_name Product name
 * @property int $quantity_delivered Original quantity delivered
 * @property \Carbon\Carbon|null $delivered_at Date item was delivered
 * @property \Carbon\Carbon|null $expires_at Expiry date of item
 * @property int $pieces_waste Quantity wasted (driver logged)
 * @property int $pieces_sold Generated: quantity_delivered - pieces_waste
 * @property string|null $notes Additional notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WasteCollectionItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'waste_collection_id',
        'destination_item_id',
        'order_item_id',
        'product_name',
        'quantity_delivered',
        'delivered_at',
        'expires_at',
        'pieces_waste',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delivered' => 'integer',
            'pieces_waste' => 'integer',
            'delivered_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function wasteCollection(): BelongsTo
    {
        return $this->belongsTo(WasteCollection::class);
    }

    public function destinationItem(): BelongsTo
    {
        return $this->belongsTo(DestinationItem::class);
    }

    // ========== ATTRIBUTES ==========

    /**
     * Get pieces sold (computed from generated column).
     * Generated column: pieces_sold = quantity_delivered - pieces_waste
     */
    protected function getPiecesSoldAttribute(): int
    {
        return max(0, $this->quantity_delivered - $this->pieces_waste);
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Check if item is expired based on expires_at date.
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->lessThan(today());
    }

    /**
     * Get number of days since expiry.
     * Returns 0 if not yet expired.
     */
    public function getDaysExpired(): int
    {
        if (! $this->isExpired()) {
            return 0;
        }

        return today()->diffInDays($this->expires_at);
    }

    /**
     * Get percentage of items that were wasted.
     * Returns: (pieces_waste / quantity_delivered) * 100
     */
    public function getWastePercentage(): float
    {
        if ($this->quantity_delivered === 0) {
            return 0;
        }

        return ($this->pieces_waste / $this->quantity_delivered) * 100;
    }

    /**
     * Validate that pieces_waste does not exceed quantity_delivered.
     */
    public function isValidWasteQuantity(): bool
    {
        return $this->pieces_waste <= $this->quantity_delivered;
    }
}
