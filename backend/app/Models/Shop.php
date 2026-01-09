<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop Model
 *
 * Represents a persistent shop/customer location for a business.
 * Can receive deliveries and have waste tracked.
 *
 * @property string $id UUID
 * @property string $business_id FK to businesses
 * @property string $external_shop_id Shop ID from external ERP
 * @property string $name Shop name
 * @property string $address Full address
 * @property float $lat Latitude
 * @property float $lng Longitude
 * @property string $contact_name Primary contact name
 * @property string $contact_phone Contact phone number
 * @property bool $track_waste Enable waste tracking for this shop
 * @property bool $is_active Shop is active
 * @property \Carbon\Carbon|null $last_synced_at Last sync timestamp
 * @property array|null $sync_metadata Custom ERP-specific metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Shop extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'external_shop_id',
        'name',
        'address',
        'lat',
        'lng',
        'contact_name',
        'contact_phone',
        'track_waste',
        'is_active',
        'last_synced_at',
        'sync_metadata',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'track_waste' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_metadata' => 'array',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }

    public function wasteCollections(): HasMany
    {
        return $this->hasMany(WasteCollection::class);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithWasteTracking($query)
    {
        return $query->where('track_waste', true);
    }

    // ========== BUSINESS LOGIC ==========

    /**
     * Get navigation URL for this shop (Google Maps deep link).
     */
    public function getNavigationUrl(): string
    {
        return "https://www.google.com/maps/dir/?api=1&destination={$this->lat},{$this->lng}&travelmode=driving";
    }

    /**
     * Get total waste items returned for a date range.
     */
    public function getTotalWasteForPeriod(\Carbon\Carbon $from, \Carbon\Carbon $to): int
    {
        return $this->wasteCollections()
            ->whereBetween('collection_date', [$from, $to])
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten()
            ->sum('pieces_waste');
    }

    /**
     * Get count of delivery destinations for this shop.
     */
    public function getDeliveryCount(): int
    {
        return $this->destinations()->completed()->count();
    }
}
