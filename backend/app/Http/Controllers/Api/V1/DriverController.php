<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DestinationStatus;
use App\Enums\TripStatus;
use App\Http\Requests\Api\V1\Driver\ArriveAtDestinationRequest;
use App\Http\Requests\Api\V1\Driver\CompleteDestinationRequest;
use App\Http\Requests\Api\V1\Driver\CompleteTripRequest;
use App\Http\Requests\Api\V1\Driver\FailDestinationRequest;
use App\Http\Requests\Api\V1\Driver\StartTripRequest;
use App\Http\Resources\Api\V1\DestinationResource;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use App\Services\Callback\DeliveryCallbackService;
use Illuminate\Http\JsonResponse;

/**
 * Driver Controller
 *
 * Handles all driver trip management endpoints.
 * Used by the Flutter mobile app for delivery operations.
 */
class DriverController extends Controller
{
    public function __construct(
        private readonly DeliveryCallbackService $callbackService,
    ) {}

    /**
     * Get today's trips for the authenticated driver.
     *
     * GET /api/v1/driver/trips/today
     */
    public function todaysTrips(): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver();

        $trips = Trip::where('driver_id', $driver->id)
            ->today()
            ->with(['deliveryRequest.destinations', 'vehicle'])
            ->orderBy('created_at')
            ->get();

        return TripResource::collection($trips)->response();
    }

    /**
     * Get trip details with destinations.
     *
     * GET /api/v1/driver/trips/{trip}
     */
    public function showTrip(Trip $trip): TripResource
    {
        $this->authorizeTrip($trip);

        return new TripResource(
            $trip->load(['deliveryRequest.destinations', 'vehicle'])
        );
    }

    /**
     * Start a trip - driver begins their route.
     *
     * POST /api/v1/driver/trips/{trip}/start
     */
    public function startTrip(Trip $trip, StartTripRequest $request): TripResource|JsonResponse
    {
        $this->authorizeTrip($trip);

        if ($trip->status !== TripStatus::NotStarted) {
            return $this->error(
                'Trip cannot be started - current status: ' . $trip->status->value,
                422
            );
        }

        // Use the model's start method
        $trip->start();

        return new TripResource(
            $trip->fresh(['deliveryRequest.destinations', 'vehicle'])
        );
    }

    /**
     * Mark arrival at a destination.
     *
     * POST /api/v1/driver/trips/{trip}/destinations/{destination}/arrive
     */
    public function arriveAtDestination(
        Trip $trip,
        Destination $destination,
        ArriveAtDestinationRequest $request
    ): DestinationResource|JsonResponse {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        if ($destination->status !== DestinationStatus::Pending) {
            return $this->error(
                'Destination cannot be marked as arrived - current status: ' . $destination->status->value,
                422
            );
        }

        // Use the model's markArrived method
        $destination->markArrived();

        return new DestinationResource($destination->fresh());
    }

    /**
     * Complete delivery at a destination.
     *
     * POST /api/v1/driver/trips/{trip}/destinations/{destination}/complete
     */
    public function completeDestination(
        Trip $trip,
        Destination $destination,
        CompleteDestinationRequest $request
    ): DestinationResource|JsonResponse {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        if (!in_array($destination->status, [DestinationStatus::Pending, DestinationStatus::Arrived])) {
            return $this->error(
                'Destination cannot be completed - current status: ' . $destination->status->value,
                422
            );
        }

        // Use the model's markCompleted method
        $destination->markCompleted(
            $request->getRecipientName(),
            $request->getNotes()
        );

        // Send callback to ERP (stub for now, DEV-1 will implement)
        $this->callbackService->sendCallback($destination);

        return new DestinationResource($destination->fresh());
    }

    /**
     * Mark destination as failed (shop closed, wrong address, etc.)
     *
     * POST /api/v1/driver/trips/{trip}/destinations/{destination}/fail
     */
    public function failDestination(
        Trip $trip,
        Destination $destination,
        FailDestinationRequest $request
    ): DestinationResource|JsonResponse {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        if (!in_array($destination->status, [DestinationStatus::Pending, DestinationStatus::Arrived])) {
            return $this->error(
                'Destination cannot be marked as failed - current status: ' . $destination->status->value,
                422
            );
        }

        // Use the model's markFailed method
        $destination->markFailed(
            $request->getReason(),
            $request->getNotes()
        );

        // Send callback to ERP with failure status
        $this->callbackService->sendCallback($destination);

        return new DestinationResource($destination->fresh());
    }

    /**
     * Complete entire trip.
     *
     * POST /api/v1/driver/trips/{trip}/complete
     */
    public function completeTrip(Trip $trip, CompleteTripRequest $request): TripResource|JsonResponse
    {
        $this->authorizeTrip($trip);

        if ($trip->status !== TripStatus::InProgress) {
            return $this->error(
                'Trip cannot be completed - current status: ' . $trip->status->value,
                422
            );
        }

        // Check all destinations are completed or failed
        $incompleteCount = $trip->deliveryRequest->destinations()
            ->whereNotIn('status', [
                DestinationStatus::Completed,
                DestinationStatus::Failed,
            ])
            ->count();

        if ($incompleteCount > 0) {
            return $this->error(
                "Cannot complete trip - {$incompleteCount} destinations not completed",
                422
            );
        }

        // Use the model's complete method
        $trip->complete($request->getTotalKm());

        return new TripResource(
            $trip->fresh(['deliveryRequest.destinations', 'vehicle'])
        );
    }

    /**
     * Get Google Maps navigation URL for a destination.
     *
     * GET /api/v1/driver/trips/{trip}/destinations/{destination}/navigate
     */
    public function getNavigationUrl(Trip $trip, Destination $destination): JsonResponse
    {
        $this->authorizeTrip($trip);
        $this->validateDestinationBelongsToTrip($trip, $destination);

        return $this->success([
            'url' => $destination->navigation_url,
            'destination' => new DestinationResource($destination),
        ]);
    }

    /**
     * Get the authenticated driver or abort.
     */
    private function getAuthenticatedDriver(): Driver
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            abort(403, 'No driver profile associated with this user');
        }

        return $driver;
    }

    /**
     * Verify the trip belongs to the authenticated driver.
     */
    private function authorizeTrip(Trip $trip): void
    {
        $driver = $this->getAuthenticatedDriver();

        if ($trip->driver_id !== $driver->id) {
            abort(403, 'This trip does not belong to you');
        }
    }

    /**
     * Verify the destination belongs to the trip's delivery request.
     */
    private function validateDestinationBelongsToTrip(Trip $trip, Destination $destination): void
    {
        if ($destination->delivery_request_id !== $trip->delivery_request_id) {
            abort(404, 'Destination not found in this trip');
        }
    }
}
