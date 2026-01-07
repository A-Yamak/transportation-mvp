<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Vehicle Model
 *
 * Represents a vehicle in our transportation fleet.
 * MVP starts with one vehicle (VW Caddy 2019).
 *
 * @property string $id UUID
 * @property string $make Vehicle manufacturer (e.g., "Volkswagen")
 * @property string $model Vehicle model (e.g., "Caddy")
 * @property int $year Manufacturing year
 * @property string $license_plate License plate number
 * @property float $total_km_driven Total kilometers driven (lifetime)
 * @property float $monthly_km_app Monthly kilometers tracked via app (reset monthly)
 * @property \Carbon\Carbon|null $acquisition_date Date vehicle was acquired
 * @property bool $is_active Whether vehicle is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Vehicle extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'make',
        'model',
        'year',
        'license_plate',
        'total_km_driven',
        'monthly_km_app',
        'tank_capacity_liters',
        'full_tank_range_km',
        'acquisition_date',
        'acquisition_km',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total_km_driven' => 'decimal:2',
            'monthly_km_app' => 'decimal:2',
            'tank_capacity_liters' => 'decimal:2',
            'full_tank_range_km' => 'decimal:2',
            'acquisition_km' => 'decimal:2',
            'acquisition_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get kilometers tracked by the app (total - acquisition).
     */
    public function getAppTrackedKmAttribute(): float
    {
        return (float) $this->total_km_driven - (float) $this->acquisition_km;
    }

    /**
     * Get fuel efficiency (km per liter).
     * Calculated from full_tank_range_km / tank_capacity_liters.
     */
    public function getKmPerLiterAttribute(): ?float
    {
        if ($this->tank_capacity_liters && $this->full_tank_range_km) {
            return round((float) $this->full_tank_range_km / (float) $this->tank_capacity_liters, 3);
        }

        return null;
    }

    /**
     * Get full vehicle name (make + model + year).
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->make} {$this->model} ({$this->year})";
    }

    /**
     * Update kilometers after a trip.
     */
    public function addKilometers(float $km): void
    {
        $this->increment('total_km_driven', $km);
        $this->increment('monthly_km_app', $km);
    }

    /**
     * Reset monthly kilometer counter (called on 1st of each month).
     */
    public function resetMonthlyKm(): void
    {
        $this->update(['monthly_km_app' => 0]);
    }

    /**
     * Driver currently assigned to this vehicle.
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * Trips made with this vehicle.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Scope for active vehicles only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
