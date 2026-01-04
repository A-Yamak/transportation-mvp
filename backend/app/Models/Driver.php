<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Driver Model
 *
 * Represents a driver who operates vehicles and executes trips.
 * Each driver is linked to a User for authentication.
 *
 * @property string $id UUID
 * @property string $user_id FK to users table
 * @property string|null $vehicle_id FK to vehicles table (assigned vehicle)
 * @property string $phone Phone number
 * @property string $license_number Driver's license number
 * @property bool $is_active Whether driver is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Driver extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'phone',
        'license_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * User account for this driver (for authentication).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Vehicle assigned to this driver.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Trips executed by this driver.
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Get driver's name from user relation.
     */
    public function getNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown';
    }

    /**
     * Today's trips for this driver.
     */
    public function todaysTrips(): HasMany
    {
        return $this->trips()->whereDate('created_at', today());
    }

    /**
     * Scope for active drivers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for drivers with assigned vehicles.
     */
    public function scopeWithVehicle($query)
    {
        return $query->whereNotNull('vehicle_id');
    }
}
