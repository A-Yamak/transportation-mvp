<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shop;
use App\Models\TupperwareMovement;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tupperware Service
 *
 * Handles tupperware container tracking for shops.
 * Manages delivery, returns, and balance tracking.
 */
class TupperwareService
{
    /**
     * Get current balance for a shop and product type.
     *
     * @param Shop $shop
     * @param string $productType
     * @return int
     */
    public function getShopBalance(Shop $shop, string $productType): int
    {
        $movements = TupperwareMovement::where('shop_id', $shop->id)
            ->where('product_type', $productType)
            ->get();

        $balance = 0;
        foreach ($movements as $movement) {
            if ($movement->isDelivery()) {
                $balance += $movement->quantity_delivered;
            } elseif ($movement->isReturn()) {
                $balance -= $movement->quantity_returned;
            }
        }

        return max(0, $balance);
    }

    /**
     * Get all balances for a shop (all product types).
     *
     * @param Shop $shop
     * @return array {product_type => balance}
     */
    public function getShopAllBalances(Shop $shop): array
    {
        $movements = TupperwareMovement::where('shop_id', $shop->id)
            ->get()
            ->groupBy('product_type');

        $balances = [];
        foreach ($movements as $productType => $typeMovements) {
            $balance = 0;
            foreach ($typeMovements as $movement) {
                if ($movement->isDelivery()) {
                    $balance += $movement->quantity_delivered;
                } elseif ($movement->isReturn()) {
                    $balance -= $movement->quantity_returned;
                }
            }
            $balances[$productType] = max(0, $balance);
        }

        return $balances;
    }

    /**
     * Record a tupperware delivery at a destination.
     *
     * @param Shop $shop
     * @param string $productType
     * @param int $quantityDelivered
     * @param array $context {trip_id, destination_id, driver_id, business_id}
     * @param string|null $notes
     * @return TupperwareMovement
     */
    public function recordDelivery(
        Shop $shop,
        string $productType,
        int $quantityDelivered,
        array $context,
        ?string $notes = null,
    ): TupperwareMovement {
        $balanceBefore = $this->getShopBalance($shop, $productType);
        $balanceAfter = $balanceBefore + $quantityDelivered;

        return TupperwareMovement::create([
            'shop_id' => $shop->id,
            'destination_id' => $context['destination_id'] ?? null,
            'trip_id' => $context['trip_id'],
            'driver_id' => $context['driver_id'],
            'business_id' => $context['business_id'],
            'product_type' => $productType,
            'quantity_delivered' => $quantityDelivered,
            'quantity_returned' => 0,
            'shop_balance_before' => $balanceBefore,
            'shop_balance_after' => $balanceAfter,
            'movement_type' => 'delivery',
            'notes' => $notes,
            'movement_at' => now(),
        ]);
    }

    /**
     * Record a tupperware pickup/return from a shop.
     *
     * @param Shop $shop
     * @param string $productType
     * @param int $quantityReturned
     * @param array $context {trip_id, destination_id, driver_id, business_id}
     * @param string|null $notes
     * @return TupperwareMovement
     */
    public function recordPickup(
        Shop $shop,
        string $productType,
        int $quantityReturned,
        array $context,
        ?string $notes = null,
    ): TupperwareMovement {
        $balanceBefore = $this->getShopBalance($shop, $productType);
        $actualReturned = min($quantityReturned, $balanceBefore); // Can't return more than available
        $balanceAfter = max(0, $balanceBefore - $actualReturned);

        return TupperwareMovement::create([
            'shop_id' => $shop->id,
            'destination_id' => $context['destination_id'] ?? null,
            'trip_id' => $context['trip_id'],
            'driver_id' => $context['driver_id'],
            'business_id' => $context['business_id'],
            'product_type' => $productType,
            'quantity_delivered' => 0,
            'quantity_returned' => $actualReturned,
            'shop_balance_before' => $balanceBefore,
            'shop_balance_after' => $balanceAfter,
            'movement_type' => 'return',
            'notes' => $notes,
            'movement_at' => now(),
        ]);
    }

    /**
     * Record a balance adjustment (correction).
     *
     * @param Shop $shop
     * @param string $productType
     * @param int $adjustment Positive or negative adjustment
     * @param string $reason Reason for adjustment
     * @return TupperwareMovement
     */
    public function recordAdjustment(
        Shop $shop,
        string $productType,
        int $adjustment,
        string $reason,
    ): TupperwareMovement {
        $balanceBefore = $this->getShopBalance($shop, $productType);
        $balanceAfter = max(0, $balanceBefore + $adjustment);

        return TupperwareMovement::create([
            'shop_id' => $shop->id,
            'trip_id' => '00000000-0000-0000-0000-000000000000', // Placeholder for system adjustments
            'driver_id' => auth()->id() ?? '00000000-0000-0000-0000-000000000000',
            'business_id' => $shop->business_id,
            'product_type' => $productType,
            'quantity_delivered' => $adjustment > 0 ? $adjustment : 0,
            'quantity_returned' => $adjustment < 0 ? abs($adjustment) : 0,
            'shop_balance_before' => $balanceBefore,
            'shop_balance_after' => $balanceAfter,
            'movement_type' => 'adjustment',
            'notes' => "Adjustment: {$reason}",
            'movement_at' => now(),
        ]);
    }

    /**
     * Get movement history for a shop and product type.
     *
     * @param Shop $shop
     * @param string $productType
     * @param int $limit
     * @return Collection
     */
    public function getMovementHistory(Shop $shop, string $productType, int $limit = 50): Collection
    {
        return TupperwareMovement::where('shop_id', $shop->id)
            ->where('product_type', $productType)
            ->orderByDesc('movement_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics for a shop and product type.
     *
     * @param Shop $shop
     * @param string $productType
     * @return array
     */
    public function getStatistics(Shop $shop, string $productType): array
    {
        $movements = TupperwareMovement::where('shop_id', $shop->id)
            ->where('product_type', $productType)
            ->get();

        $totalDelivered = $movements->where('movement_type', 'delivery')->sum('quantity_delivered');
        $totalReturned = $movements->where('movement_type', 'return')->sum('quantity_returned');
        $currentBalance = $this->getShopBalance($shop, $productType);

        return [
            'current_balance' => $currentBalance,
            'total_delivered' => $totalDelivered,
            'total_returned' => $totalReturned,
            'net_out' => $totalDelivered - $totalReturned,
            'movement_count' => $movements->count(),
            'last_movement_at' => $movements->max('movement_at'),
        ];
    }

    /**
     * Get high balance shops (potential refund risk).
     *
     * @param string $productType
     * @param int $threshold
     * @return Collection
     */
    public function getHighBalanceShops(string $productType, int $threshold = 50): Collection
    {
        $shops = Shop::all();
        $highBalance = [];

        foreach ($shops as $shop) {
            $balance = $this->getShopBalance($shop, $productType);
            if ($balance >= $threshold) {
                $highBalance[] = [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'balance' => $balance,
                ];
            }
        }

        return collect($highBalance);
    }

    /**
     * Get low balance shops (reorder needed).
     *
     * @param string $productType
     * @param int $threshold
     * @return Collection
     */
    public function getLowBalanceShops(string $productType, int $threshold = 5): Collection
    {
        $shops = Shop::all();
        $lowBalance = [];

        foreach ($shops as $shop) {
            $balance = $this->getShopBalance($shop, $productType);
            if ($balance <= $threshold && $balance > 0) {
                $lowBalance[] = [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'balance' => $balance,
                ];
            }
        }

        return collect($lowBalance);
    }
}
