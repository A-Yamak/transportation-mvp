<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PricingTier Model
 *
 * Defines pricing for deliveries: total_km × price_per_km.
 * Different tiers can exist for different business types.
 *
 * @property string $id UUID
 * @property string $name Tier name (e.g., "Standard", "Premium")
 * @property BusinessType|null $business_type Business type this applies to (null = default)
 * @property float $price_per_km Price per kilometer
 * @property float $base_fee Fixed fee per delivery
 * @property float $minimum_cost Minimum cost for any delivery
 * @property \Carbon\Carbon $effective_date Date this pricing becomes effective
 * @property bool $is_active Whether this tier is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PricingTier extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'business_type',
        'price_per_km',
        'base_fee',
        'minimum_cost',
        'effective_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'price_per_km' => 'decimal:4',
            'base_fee' => 'decimal:2',
            'minimum_cost' => 'decimal:2',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Calculate cost for given kilometers.
     */
    public function calculateCost(float $kilometers): float
    {
        return round($kilometers * $this->price_per_km, 2);
    }

    /**
     * Get the current active pricing tier for a business type.
     */
    public static function getCurrentForBusinessType(?BusinessType $businessType): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where(function ($query) use ($businessType) {
                $query->where('business_type', $businessType)
                    ->orWhereNull('business_type');
            })
            ->where('effective_date', '<=', now())
            ->orderByDesc('effective_date')
            ->orderByRaw('business_type IS NULL') // Specific type takes priority
            ->first();
    }

    /**
     * Scope for active tiers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for currently effective tiers.
     */
    public function scopeEffective($query)
    {
        return $query->where('effective_date', '<=', now());
    }
}
