<?php

declare(strict_types=1);

namespace App\Services\TripAssignment;

use App\Enums\TripStatus;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;

/**
 * Handles automatic trip assignment for delivery requests.
 *
 * Used when auto-assignment is enabled (single driver operations).
 * Creates a Trip and assigns it to an available driver.
 */
class AutoAssignmentService
{
    /**
     * Attempt to auto-assign a delivery request to an available driver.
     *
     * @return Trip|null The created trip, or null if assignment failed
     */
    public function assign(DeliveryRequest $deliveryRequest): ?Trip
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $driver = $this->findAvailableDriver();
        if (! $driver) {
            Log::warning('Auto-assignment failed: No available driver found', [
                'delivery_request_id' => $deliveryRequest->id,
            ]);

            return null;
        }

        $vehicle = $this->findVehicleForDriver($driver);
        if (! $vehicle) {
            Log::warning('Auto-assignment failed: No vehicle available for driver', [
                'delivery_request_id' => $deliveryRequest->id,
                'driver_id' => $driver->id,
            ]);

            return null;
        }

        $trip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'delivery_request_id' => $deliveryRequest->id,
            'status' => TripStatus::NotStarted,
        ]);

        Log::info('Delivery request auto-assigned to driver', [
            'delivery_request_id' => $deliveryRequest->id,
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->user->name,
        ]);

        return $trip;
    }

    /**
     * Check if auto-assignment is enabled.
     */
    public function isEnabled(): bool
    {
        return config('delivery.auto_assign.enabled', false);
    }

    /**
     * Find an available driver based on the configured strategy.
     */
    protected function findAvailableDriver(): ?Driver
    {
        $strategy = config('delivery.auto_assign.strategy', 'single');
        $specificDriverId = config('delivery.auto_assign.driver_id');

        // If a specific driver is configured, use that
        if ($specificDriverId) {
            return Driver::where('id', $specificDriverId)
                ->where('is_active', true)
                ->first();
        }

        // For 'single' strategy, just get the first active driver
        if ($strategy === 'single') {
            return Driver::where('is_active', true)
                ->first();
        }

        // For 'round_robin' strategy, get the driver with fewest trips today
        if ($strategy === 'round_robin') {
            return Driver::where('is_active', true)
                ->withCount(['trips' => function ($query) {
                    $query->whereDate('created_at', today());
                }])
                ->orderBy('trips_count', 'asc')
                ->first();
        }

        return null;
    }

    /**
     * Find a vehicle for the driver.
     *
     * Returns the driver's assigned vehicle, or any available vehicle.
     */
    protected function findVehicleForDriver(Driver $driver): ?Vehicle
    {
        // First try the driver's assigned vehicle
        if ($driver->vehicle && $driver->vehicle->is_active) {
            return $driver->vehicle;
        }

        // Fallback to any active vehicle
        return Vehicle::where('is_active', true)->first();
    }
}
