<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TripStatus;
use App\Enums\TripType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trip Model
 *
 * A driver's execution of a delivery request or waste collection.
 * Tracks actual kilometers driven via GPS.
 *
 * @property string $id UUID
 * @property string $delivery_request_id FK to delivery_requests
 * @property string $driver_id FK to drivers
 * @property string $vehicle_id FK to vehicles
 * @property TripType $trip_type Type: 'delivery' or 'waste_collection'
 * @property TripStatus $status Current status
 * @property \Carbon\Carbon|null $started_at When trip started
 * @property \Carbon\Carbon|null $completed_at When trip completed
 * @property float|null $actual_km Actual kilometers from GPS
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Trip extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'delivery_request_id',
        'driver_id',
        'vehicle_id',
        'trip_type',
        'status',
        'started_at',
        'completed_at',
        'actual_km',
    ];

    protected function casts(): array
    {
        return [
            'trip_type' => TripType::class,
            'status' => TripStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'actual_km' => 'decimal:2',
        ];
    }

    /**
     * Delivery request being fulfilled.
     */
    public function deliveryRequest(): BelongsTo
    {
        return $this->belongsTo(DeliveryRequest::class);
    }

    /**
     * Driver executing this trip.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Vehicle used for this trip.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Waste items collected during this trip (if waste collection trip).
     */
    public function wasteCollections(): HasMany
    {
        return $this->hasMany(WasteCollection::class);
    }

    /**
     * Get destinations through delivery request.
     */
    public function getDestinationsAttribute()
    {
        return $this->deliveryRequest?->destinations;
    }

    /**
     * Check if this is a delivery trip.
     */
    public function isDelivery(): bool
    {
        return $this->trip_type === TripType::Delivery;
    }

    /**
     * Check if this is a waste collection trip.
     */
    public function isWasteCollection(): bool
    {
        return $this->trip_type === TripType::WasteCollection;
    }

    /**
     * Start the trip.
     */
    public function start(): void
    {
        $this->update([
            'status' => TripStatus::InProgress,
            'started_at' => now(),
        ]);

        $this->deliveryRequest->update([
            'status' => \App\Enums\DeliveryRequestStatus::InProgress,
        ]);
    }

    /**
     * Complete the trip.
     */
    public function complete(float $actualKm): void
    {
        $this->update([
            'status' => TripStatus::Completed,
            'completed_at' => now(),
            'actual_km' => $actualKm,
        ]);

        // Update vehicle kilometers
        $this->vehicle->addKilometers($actualKm);

        // Mark delivery request as completed
        $this->deliveryRequest->markCompleted($actualKm);
    }

    /**
     * Cancel the trip.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => TripStatus::Cancelled,
        ]);
    }

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Scope for today's trips.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for active (in-progress) trips.
     */
    public function scopeActive($query)
    {
        return $query->where('status', TripStatus::InProgress);
    }

    /**
     * Scope for completed trips.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', TripStatus::Completed);
    }

    /**
     * Scope for delivery trips only.
     */
    public function scopeDelivery($query)
    {
        return $query->where('trip_type', TripType::Delivery->value);
    }

    /**
     * Scope for waste collection trips only.
     */
    public function scopeWasteCollection($query)
    {
        return $query->where('trip_type', TripType::WasteCollection->value);
    }
}
