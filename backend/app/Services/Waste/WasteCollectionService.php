<?php

declare(strict_types=1);

namespace App\Services\Waste;

use App\Models\Shop;
use App\Models\WasteCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Waste Collection Service
 *
 * Handles waste collection business logic including:
 * - Calculating sold quantities (delivered - waste)
 * - Generating waste reports
 * - Aggregating waste data
 */
class WasteCollectionService
{
    /**
     * Calculate sold quantity for a specific item at a shop.
     *
     * @param  Shop  $shop
     * @param  string  $orderItemId
     * @param  Carbon  $from
     * @param  Carbon  $to
     * @return array{delivered: int, waste: int, sold: int}
     */
    public function calculateSoldQuantity(
        Shop $shop,
        string $orderItemId,
        Carbon $from,
        Carbon $to
    ): array {
        // Sum delivered quantity from destination_items linked to this shop
        $delivered = $shop->destinations()
            ->whereBetween('completed_at', [$from, $to])
            ->whereHas('items', fn ($q) => $q->where('order_item_id', $orderItemId))
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten()
            ->where('order_item_id', $orderItemId)
            ->sum('quantity_delivered');

        // Sum waste quantity for this item
        $waste = $shop->wasteCollections()
            ->whereBetween('collection_date', [$from, $to])
            ->with('items')
            ->get()
            ->pluck('items')
            ->flatten()
            ->where('order_item_id', $orderItemId)
            ->sum('pieces_waste');

        $sold = max(0, $delivered - $waste);

        return [
            'delivered' => $delivered,
            'waste' => $waste,
            'sold' => $sold,
        ];
    }

    /**
     * Get aggregated waste report for a shop.
     *
     * @param  Shop  $shop
     * @param  Carbon  $from
     * @param  Carbon  $to
     * @return array
     */
    public function getShopWasteReport(Shop $shop, Carbon $from, Carbon $to): array
    {
        $wasteCollections = $shop->wasteCollections()
            ->whereBetween('collection_date', [$from, $to])
            ->with('items')
            ->get();

        $groupedByItem = $wasteCollections
            ->pluck('items')
            ->flatten()
            ->groupBy('order_item_id')
            ->map(function ($items) {
                return [
                    'order_item_id' => $items->first()->order_item_id,
                    'product_name' => $items->first()->product_name,
                    'total_waste' => $items->sum('pieces_waste'),
                    'total_sold' => $items->sum('pieces_sold'),
                    'total_delivered' => $items->sum('quantity_delivered'),
                    'waste_percentage' => $this->calculatePercentage(
                        $items->sum('pieces_waste'),
                        $items->sum('quantity_delivered')
                    ),
                    'reasons' => $items->groupBy('reason')->map->count()->toArray(),
                ];
            });

        return [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'total_waste_collections' => $wasteCollections->count(),
            'total_waste_items' => $wasteCollections->pluck('items')->flatten()->count(),
            'total_waste_quantity' => $wasteCollections->pluck('items')->flatten()->sum('pieces_waste'),
            'total_sold_quantity' => $wasteCollections->pluck('items')->flatten()->sum('pieces_sold'),
            'total_delivered_quantity' => $wasteCollections->pluck('items')->flatten()->sum('quantity_delivered'),
            'items' => $groupedByItem->values()->toArray(),
        ];
    }

    /**
     * Generate waste collection route for shops with expected waste.
     *
     * @param  Shop|null  $business
     * @return array<int, string> Shop IDs in proximity order
     */
    public function generateWasteCollectionRoute(?string $businessId = null): array
    {
        $query = Shop::where('track_waste', true)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('is_active', true);

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $shops = $query->get(['id', 'lat', 'lng']);

        if ($shops->isEmpty()) {
            return [];
        }

        // Simple proximity ordering (can be enhanced with Google Maps optimization)
        // For now, order by latitude then longitude
        return $shops
            ->sortBy(['lat', 'lng'])
            ->pluck('id')
            ->values()
            ->toArray();
    }

    /**
     * Get expected waste for shops.
     *
     * @param  string|null  $businessId
     * @return array
     */
    public function getExpectedWaste(?string $businessId = null): array
    {
        $query = Shop::where('track_waste', true)
            ->where('is_active', true)
            ->whereNotNull('expected_waste_date');

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $shops = $query
            ->where('expected_waste_date', '<=', today())
            ->with('wasteCollections')
            ->get();

        return $shops->map(function (Shop $shop) {
            // Count uncollected waste items for this shop
            $uncollectedItems = $shop->wasteCollections()
                ->where('collected_at', null)
                ->with('items')
                ->get()
                ->pluck('items')
                ->flatten()
                ->count();

            return [
                'shop_id' => $shop->id,
                'external_shop_id' => $shop->external_shop_id,
                'name' => $shop->name,
                'address' => $shop->address,
                'lat' => $shop->lat?->__toString(),
                'lng' => $shop->lng?->__toString(),
                'expected_waste_date' => $shop->expected_waste_date?->toDateString(),
                'uncollected_waste_items_count' => $uncollectedItems,
            ];
        })->toArray();
    }

    /**
     * Calculate percentage value.
     *
     * @param  int  $part
     * @param  int  $whole
     * @return float
     */
    protected function calculatePercentage(int $part, int $whole): float
    {
        if ($whole === 0) {
            return 0.0;
        }

        return ($part / $whole) * 100;
    }
}
