<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryRequestStatus;
use App\Enums\TripStatus;
use App\Http\Requests\Api\V1\AssignTripRequest;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

/**
 * Trip Assignment Controller
 *
 * Handles trip assignment (dispatch) operations.
 * Used by admin/dispatch to assign delivery requests to drivers.
 */
class TripAssignmentController extends Controller
{
    /**
     * Assign a delivery request to a driver.
     *
     * POST /api/v1/trips/assign
     */
    public function assign(AssignTripRequest $request): TripResource|JsonResponse
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->getDeliveryRequestId());
        $driver = Driver::findOrFail($request->getDriverId());
        $vehicle = Vehicle::findOrFail($request->getVehicleId());

        // Check if delivery request already has a trip
        if ($deliveryRequest->trip()->exists()) {
            return $this->error(
                'Delivery request already assigned to a trip',
                422
            );
        }

        // Verify driver is active
        if (!$driver->is_active) {
            return $this->error(
                'Cannot assign to inactive driver',
                422
            );
        }

        // Verify vehicle is active
        if (!$vehicle->is_active) {
            return $this->error(
                'Cannot assign to inactive vehicle',
                422
            );
        }

        // Create the trip
        $trip = Trip::create([
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'status' => TripStatus::NotStarted,
        ]);

        // Update delivery request status
        $deliveryRequest->update([
            'status' => DeliveryRequestStatus::Accepted,
        ]);

        return (new TripResource(
            $trip->load(['deliveryRequest.destinations', 'vehicle', 'driver'])
        ))->response()->setStatusCode(201);
    }

    /**
     * List unassigned delivery requests.
     *
     * GET /api/v1/trips/unassigned
     */
    public function unassigned(): JsonResponse
    {
        $deliveryRequests = DeliveryRequest::where('status', DeliveryRequestStatus::Pending)
            ->whereDoesntHave('trip')
            ->with('destinations')
            ->orderBy('created_at')
            ->paginate();

        return $this->paginated($deliveryRequests);
    }

    /**
     * List available drivers (active with vehicles).
     *
     * GET /api/v1/trips/available-drivers
     */
    public function availableDrivers(): JsonResponse
    {
        $drivers = Driver::active()
            ->withVehicle()
            ->with(['user', 'vehicle'])
            ->get();

        return $this->success($drivers->map(fn ($driver) => [
            'id' => $driver->id,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'vehicle' => $driver->vehicle ? [
                'id' => $driver->vehicle->id,
                'make' => $driver->vehicle->make,
                'model' => $driver->vehicle->model,
                'license_plate' => $driver->vehicle->license_plate,
            ] : null,
        ]));
    }
}
