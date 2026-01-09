<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WasteCollection Model
 *
 * Represents waste collected from a shop on a specific date.
 * Can be linked to a waste collection trip (driver pickup).
 *
 * @property string $id UUID
 * @property string $shop_id FK to shops
 * @property string|null $trip_id FK to trips (waste collection trip)
 * @property string|null $driver_id FK to users (driver who collected)
 * @property string $business_id FK to businesses
 * @property \Carbon\Carbon $collection_date Date waste was expected/collected
 * @property int $total_items_count Count of waste items
 * @property \Carbon\Carbon|null $collected_at Timestamp when collected
 * @property string|null $driver_notes Notes from driver
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WasteCollection extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shop_id',
        'trip_id',
        'driver_id',
        'business_id',
        'collection_date',
        'total_items_count',
        'collected_at',
        'driver_notes',
    ];

    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'collected_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(WasteCollectionItem::class);
    }

    // ========== SCOPES ==========

    public function scopeCollected($query)
    {
        return $query->whereNotNull('collected_at');
    }

    public function scopeUncollected($query)
    {
        return $query->whereNull('collected_at');
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Check if this waste collection has been collected.
     */
    public function isCollected(): bool
    {
        return $this->collected_at !== null;
    }

    /**
     * Get total pieces wasted across all items.
     */
    public function getTotalWastePieces(): int
    {
        return $this->items()->sum('pieces_waste');
    }

    /**
     * Get total pieces sold (delivered - waste).
     */
    public function getTotalSoldPieces(): int
    {
        return $this->items()->sum('pieces_sold');
    }

    /**
     * Get total delivered pieces (original quantity).
     */
    public function getTotalDeliveredPieces(): int
    {
        return $this->items()->sum('quantity_delivered');
    }
}
