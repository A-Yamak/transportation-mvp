<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryRequestStatus;
use App\Http\Requests\Api\V1\DeliveryRequest\StoreDeliveryRequestRequest;
use App\Http\Resources\Api\V1\DeliveryRequestResource;
use App\Http\Resources\Api\V1\DestinationResource;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Services\GoogleMaps\RouteOptimizer;
use App\Services\Pricing\CostCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles delivery request operations for ERP integrations.
 *
 * This controller provides endpoints for businesses to:
 * - Create delivery requests with multiple destinations
 * - List and view their delivery requests
 * - Get route optimization data
 * - Cancel pending requests
 */
class DeliveryRequestController extends Controller
{
    public function __construct(
        protected readonly RouteOptimizer $routeOptimizer,
        protected readonly CostCalculator $costCalculator,
    ) {}

    /**
     * Create a new delivery request with route optimization.
     *
     * POST /api/v1/delivery-requests
     *
     * @param StoreDeliveryRequestRequest $request
     * @return JsonResponse
     */
    public function store(StoreDeliveryRequestRequest $request): JsonResponse
    {
        $business = $request->getBusiness();
        $destinations = $request->getDestinations();

        // Get factory/depot location as start point
        $startPoint = config('google-maps.factory_location');

        // Optimize route via Google Maps
        $optimizedRoute = $this->routeOptimizer->optimize($destinations, $startPoint);

        // Calculate estimated cost
        $totalKm = $optimizedRoute['total_distance_km'];
        $estimatedCost = $this->costCalculator->estimateCostByDistance(
            $totalKm,
            $business->business_type
        );

        // Create delivery request and destinations in a transaction
        $deliveryRequest = DB::transaction(function () use (
            $business,
            $destinations,
            $optimizedRoute,
            $totalKm,
            $estimatedCost,
            $request
        ) {
            $deliveryRequest = DeliveryRequest::create([
                'business_id' => $business->id,
                'status' => DeliveryRequestStatus::Pending,
                'total_km' => $totalKm,
                'estimated_cost' => $estimatedCost,
                'optimized_route' => [
                    'polyline' => $optimizedRoute['polyline'],
                    'waypoint_order' => $optimizedRoute['optimized_order'],
                    'total_duration_minutes' => $optimizedRoute['total_duration_minutes'],
                ],
                'notes' => $request->getNotes(),
                'requested_at' => now(),
            ]);

            // Create destinations in optimized order
            $optimizedOrder = $optimizedRoute['optimized_order'];

            // If no optimization was performed (single destination) or order mismatch, use original order
            if (empty($optimizedOrder) || count($optimizedOrder) !== count($destinations)) {
                $optimizedOrder = array_keys($destinations);
            }

            foreach ($optimizedOrder as $sequenceIndex => $originalIndex) {
                // Safety check for valid index
                if (! isset($destinations[$originalIndex])) {
                    continue;
                }

                $dest = $destinations[$originalIndex];

                $destination = Destination::create([
                    'delivery_request_id' => $deliveryRequest->id,
                    'external_id' => $dest['external_id'],
                    'address' => $dest['address'],
                    'lat' => $dest['lat'],
                    'lng' => $dest['lng'],
                    'sequence_order' => $sequenceIndex + 1, // 1-based sequence
                    'notes' => $dest['notes'] ?? null,
                ]);

                // Create destination items if provided (for partial delivery tracking)
                if (! empty($dest['items'])) {
                    foreach ($dest['items'] as $item) {
                        $destination->items()->create([
                            'order_item_id' => $item['order_item_id'],
                            'name' => $item['name'] ?? null,
                            'quantity_ordered' => $item['quantity_ordered'],
                            'quantity_delivered' => 0, // Will be updated by driver on completion
                        ]);
                    }
                }
            }

            return $deliveryRequest;
        });

        // Load destinations with items for response
        $deliveryRequest->load('destinations.items');

        return (new DeliveryRequestResource($deliveryRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * List delivery requests for the authenticated business.
     *
     * GET /api/v1/delivery-requests
     *
     * Query params:
     * - status: Filter by status (pending, accepted, in_progress, completed, cancelled)
     * - per_page: Items per page (default 15)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $business = $request->get('business');

        $query = DeliveryRequest::where('business_id', $business->id)
            ->with('destinations')
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $status = DeliveryRequestStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        return $this->paginated($paginator, DeliveryRequestResource::class);
    }

    /**
     * Show a specific delivery request.
     *
     * GET /api/v1/delivery-requests/{deliveryRequest}
     *
     * @param Request $request
     * @param DeliveryRequest $deliveryRequest
     * @return JsonResponse|DeliveryRequestResource
     */
    public function show(Request $request, DeliveryRequest $deliveryRequest): JsonResponse|DeliveryRequestResource
    {
        $business = $request->get('business');

        // Ensure the delivery request belongs to the authenticated business
        if ($deliveryRequest->business_id !== $business->id) {
            return $this->notFound('Delivery request not found');
        }

        $deliveryRequest->load('destinations');

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Get route data for a delivery request.
     *
     * GET /api/v1/delivery-requests/{deliveryRequest}/route
     *
     * Returns the optimized route data including polyline and destinations
     * in sequence order for map display.
     *
     * @param Request $request
     * @param DeliveryRequest $deliveryRequest
     * @return JsonResponse
     */
    public function route(Request $request, DeliveryRequest $deliveryRequest): JsonResponse
    {
        $business = $request->get('business');

        // Ensure the delivery request belongs to the authenticated business
        if ($deliveryRequest->business_id !== $business->id) {
            return $this->notFound('Delivery request not found');
        }

        $deliveryRequest->load('destinations');

        return $this->success([
            'id' => $deliveryRequest->id,
            'polyline' => $deliveryRequest->optimized_route['polyline'] ?? null,
            'waypoint_order' => $deliveryRequest->optimized_route['waypoint_order'] ?? [],
            'total_km' => $deliveryRequest->total_km,
            'total_duration_minutes' => $deliveryRequest->optimized_route['total_duration_minutes'] ?? null,
            'factory_location' => config('google-maps.factory_location'),
            'destinations' => DestinationResource::collection($deliveryRequest->destinations),
        ]);
    }

    /**
     * Cancel a pending delivery request.
     *
     * POST /api/v1/delivery-requests/{deliveryRequest}/cancel
     *
     * Only pending requests can be cancelled.
     *
     * @param Request $request
     * @param DeliveryRequest $deliveryRequest
     * @return JsonResponse|DeliveryRequestResource
     */
    public function cancel(Request $request, DeliveryRequest $deliveryRequest): JsonResponse|DeliveryRequestResource
    {
        $business = $request->get('business');

        // Ensure the delivery request belongs to the authenticated business
        if ($deliveryRequest->business_id !== $business->id) {
            return $this->notFound('Delivery request not found');
        }

        // Only allow cancellation of pending requests
        if ($deliveryRequest->status !== DeliveryRequestStatus::Pending) {
            return $this->error(
                'Only pending delivery requests can be cancelled.',
                422
            );
        }

        $deliveryRequest->update([
            'status' => DeliveryRequestStatus::Cancelled,
        ]);

        $deliveryRequest->load('destinations');

        return new DeliveryRequestResource($deliveryRequest);
    }
}
