<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\External\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\External\V1\Shop\SyncShopsRequest;
use App\Http\Requests\Api\External\V1\Shop\UpdateShopRequest;
use App\Http\Resources\Api\External\V1\ShopResource;
use App\Models\Business;
use App\Models\Shop;
use App\Services\Shop\ShopSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shop Controller (External API)
 *
 * Manages shop synchronization and CRUD operations for B2B integrations.
 * Authentication: API Key via X-API-Key header
 */
class ShopController extends Controller
{
    public function __construct(
        protected ShopSyncService $shopSyncService,
    ) {}

    /**
     * Bulk sync shops from external business system.
     *
     * POST /api/external/v1/shops/sync
     *
     * @param  SyncShopsRequest  $request
     * @return JsonResponse
     */
    public function sync(SyncShopsRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $result = $this->shopSyncService->syncShops(
            $business,
            $request->getShops()
        );

        return response()->json([
            'data' => $result,
            'message' => "Shops synced successfully: {$result['created']} created, {$result['updated']} updated, {$result['deleted']} deleted",
        ]);
    }

    /**
     * List shops for authenticated business.
     *
     * GET /api/external/v1/shops
     *
     * Query Parameters:
     * - is_active: boolean (filter by active status)
     * - track_waste: boolean (filter by waste tracking)
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $query = Shop::where('business_id', $business->id);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('track_waste')) {
            $query->where('track_waste', $request->boolean('track_waste'));
        }

        $shops = $query
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => ShopResource::collection($shops)->resolve(),
            'pagination' => [
                'current_page' => $shops->currentPage(),
                'last_page' => $shops->lastPage(),
                'per_page' => $shops->perPage(),
                'total' => $shops->total(),
            ],
        ]);
    }

    /**
     * Get shop by external_shop_id.
     *
     * GET /api/external/v1/shops/{externalShopId}
     *
     * @param  Request  $request
     * @param  string  $externalShopId
     * @return JsonResponse
     */
    public function show(Request $request, string $externalShopId): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $shop = Shop::where('business_id', $business->id)
            ->where('external_shop_id', $externalShopId)
            ->firstOrFail();

        return response()->json([
            'data' => new ShopResource($shop),
        ]);
    }

    /**
     * Update shop by external_shop_id.
     *
     * PUT /api/external/v1/shops/{externalShopId}
     *
     * @param  UpdateShopRequest  $request
     * @param  string  $externalShopId
     * @return JsonResponse
     */
    public function update(UpdateShopRequest $request, string $externalShopId): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $shop = Shop::where('business_id', $business->id)
            ->where('external_shop_id', $externalShopId)
            ->firstOrFail();

        $shop->update($request->validated());

        return response()->json([
            'data' => new ShopResource($shop->fresh()),
            'message' => 'Shop updated successfully',
        ]);
    }

    /**
     * Deactivate shop by external_shop_id.
     *
     * DELETE /api/external/v1/shops/{externalShopId}
     *
     * @param  Request  $request
     * @param  string  $externalShopId
     * @return JsonResponse
     */
    public function destroy(Request $request, string $externalShopId): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $shop = Shop::where('business_id', $business->id)
            ->where('external_shop_id', $externalShopId)
            ->firstOrFail();

        $shop->update(['is_active' => false]);

        return response()->json([
            'data' => new ShopResource($shop->fresh()),
            'message' => 'Shop deactivated successfully',
        ]);
    }
}
