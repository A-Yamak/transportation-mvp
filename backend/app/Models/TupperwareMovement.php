<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TupperwareMovement Model
 *
 * Tracks tupperware (boxes, trays, bags) delivery and return movements.
 * Used to maintain shop balances of reusable containers.
 *
 * @property string $id UUID
 * @property string $shop_id FK to shops
 * @property string|null $destination_id FK to destinations (delivery point)
 * @property string $trip_id FK to trips
 * @property string $driver_id FK to users (driver)
 * @property string $business_id FK to businesses
 * @property string $product_type Container type: "box", "tray", "bag", etc.
 * @property int $quantity_delivered Number of containers delivered
 * @property int $quantity_returned Number of containers returned
 * @property int $shop_balance_before Balance before movement
 * @property int $shop_balance_after Balance after movement
 * @property string $movement_type "delivery", "return", or "adjustment"
 * @property string|null $notes Optional notes about the movement
 * @property \Carbon\Carbon $movement_at Timestamp when movement occurred
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TupperwareMovement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shop_id',
        'destination_id',
        'trip_id',
        'driver_id',
        'business_id',
        'product_type',
        'quantity_delivered',
        'quantity_returned',
        'shop_balance_before',
        'shop_balance_after',
        'movement_type',
        'notes',
        'movement_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

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
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    // ========== SCOPES ==========

    public function scopeDeliveries($query)
    {
        return $query->where('movement_type', 'delivery');
    }

    public function scopeReturns($query)
    {
        return $query->where('movement_type', 'return');
    }

    public function scopeForProductType($query, string $productType)
    {
        return $query->where('product_type', $productType);
    }

    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Get the net change in balance (negative = items out, positive = items in).
     */
    public function getNetChangeAttribute(): int
    {
        return $this->shop_balance_after - $this->shop_balance_before;
    }

    /**
     * Check if balance increased (return movement).
     */
    public function isReturn(): bool
    {
        return $this->movement_type === 'return';
    }

    /**
     * Check if balance decreased (delivery movement).
     */
    public function isDelivery(): bool
    {
        return $this->movement_type === 'delivery';
    }
}
