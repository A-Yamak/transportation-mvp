<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\External\V1\Waste\SetExpectedWasteRequest;
use App\Models\Business;
use App\Services\Shop\ShopSyncService;
use App\Services\Waste\WasteCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Waste Collection Controller (External API)
 *
 * Manages waste collection operations and expected waste coordination.
 * Authentication: API Key via X-API-Key header
 */
class WasteCollectionController extends Controller
{
    public function __construct(
        protected WasteCollectionService $wasteCollectionService,
        protected ShopSyncService $shopSyncService,
    ) {}

    /**
     * Get shops with expected waste to be collected.
     *
     * GET /api/external/v1/waste/expected
     *
     * Returns shops where expected_waste_date <= today.
     * Used to plan waste collection routes.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function expected(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $expectedWaste = $this->wasteCollectionService->getExpectedWaste($business->id);

        return response()->json([
            'data' => $expectedWaste,
            'meta' => [
                'total_shops' => count($expectedWaste),
                'generation_date' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Set expected waste dates for shops.
     *
     * POST /api/external/v1/waste/expected
     *
     * Request body:
     * {
     *   "shops": [
     *     {
     *       "external_shop_id": "SHOP-001",
     *       "expected_waste_date": "2026-01-15"
     *     }
     *   ]
     * }
     *
     * @param  SetExpectedWasteRequest  $request
     * @return JsonResponse
     */
    public function setExpected(SetExpectedWasteRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $updated = $this->shopSyncService->setExpectedWasteDates(
            $business,
            $request->getShops()
        );

        return response()->json([
            'data' => [
                'updated' => $updated,
            ],
            'message' => "Expected waste dates updated for {$updated} shop(s)",
        ]);
    }
}
