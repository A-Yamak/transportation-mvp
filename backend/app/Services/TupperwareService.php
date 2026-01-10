<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Destination;
use App\Models\Shop;
use App\Models\TupperwareMovement;
use Illuminate\Support\Collection;

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
     * @return array<string, int> {product_type => balance}
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
     */
    public function recordDelivery(
        Shop $shop,
        Destination $destination,
        string $productType,
        int $quantityDelivered,
        ?string $notes = null,
    ): TupperwareMovement {
        $balanceBefore = $this->getShopBalance($shop, $productType);
        $balanceAfter = $balanceBefore + $quantityDelivered;

        $trip = $destination->getTrip();

        return TupperwareMovement::create([
            'shop_id' => $shop->id,
            'destination_id' => $destination->id,
            'trip_id' => $trip?->id,
            'driver_id' => $trip?->driver_id ?? auth()->id(),
            'business_id' => $trip?->deliveryRequest?->business_id ?? $shop->business_id,
            'product_type' => $productType,
            'quantity_delivered' => $quantityDelivered,
            'quantity_returned' => 0,
            'shop_balance_before' => $balanceBefore,
            'shop_balance_after' => $balanceAfter,
            'movement_type' => MovementType::Delivery,
            'notes' => $notes,
            'movement_at' => now(),
        ]);
    }

    /**
     * Record a tupperware pickup/return from a shop.
     */
    public function recordPickup(
        Shop $shop,
        Destination $destination,
        string $productType,
        int $quantityReturned,
        ?string $notes = null,
    ): TupperwareMovement {
        $balanceBefore = $this->getShopBalance($shop, $productType);
        $actualReturned = min($quantityReturned, $balanceBefore); // Can't return more than available
        $balanceAfter = max(0, $balanceBefore - $quantityReturned);

        $trip = $destination->getTrip();

        return TupperwareMovement::create([
            'shop_id' => $shop->id,
            'destination_id' => $destination->id,
            'trip_id' => $trip?->id,
            'driver_id' => $trip?->driver_id ?? auth()->id(),
            'business_id' => $trip?->deliveryRequest?->business_id ?? $shop->business_id,
            'product_type' => $productType,
            'quantity_delivered' => 0,
            'quantity_returned' => $quantityReturned,
            'shop_balance_before' => $balanceBefore,
            'shop_balance_after' => $balanceAfter,
            'movement_type' => MovementType::Return,
            'notes' => $notes,
            'movement_at' => now(),
        ]);
    }

    /**
     * Record a balance adjustment (correction).
     */
    public function recordAdjustment(
        Shop $shop,
        Destination $destination,
        string $productType,
        int $adjustment,
        ?string $notes = null,
    ): TupperwareMovement {
        $balanceBefore = $this->getShopBalance($shop, $productType);
        $balanceAfter = max(0, $balanceBefore + $adjustment);

        $trip = $destination->getTrip();

        return TupperwareMovement::create([
            'shop_id' => $shop->id,
            'destination_id' => $destination->id,
            'trip_id' => $trip?->id,
            'driver_id' => $trip?->driver_id ?? auth()->id(),
            'business_id' => $trip?->deliveryRequest?->business_id ?? $shop->business_id,
            'product_type' => $productType,
            'quantity_delivered' => $adjustment > 0 ? $adjustment : 0,
            'quantity_returned' => $adjustment < 0 ? abs($adjustment) : 0,
            'shop_balance_before' => $balanceBefore,
            'shop_balance_after' => $balanceAfter,
            'movement_type' => MovementType::Adjustment,
            'notes' => $notes,
            'movement_at' => now(),
        ]);
    }

    /**
     * Get movement history for a shop and product type.
     */
    public function getMovementHistory(Shop $shop, string $productType, int $limit = 50): Collection
    {
        return TupperwareMovement::where('shop_id', $shop->id)
            ->where('product_type', $productType)
            ->orderBy('movement_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics for a shop and product type.
     */
    public function getStatistics(Shop $shop, string $productType): array
    {
        $movements = TupperwareMovement::where('shop_id', $shop->id)
            ->where('product_type', $productType)
            ->get();

        $totalDelivered = $movements->sum('quantity_delivered');
        $totalReturned = $movements->sum('quantity_returned');
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
     * Checks total balance across all product types.
     */
    public function getHighBalanceShops(int $threshold = 50): Collection
    {
        $shops = Shop::all();
        $highBalance = collect();

        foreach ($shops as $shop) {
            $allBalances = $this->getShopAllBalances($shop);
            $totalBalance = array_sum($allBalances);
            if ($totalBalance >= $threshold) {
                $shop->total_balance = $totalBalance;
                $highBalance->push($shop);
            }
        }

        return $highBalance;
    }

    /**
     * Get low balance shops (reorder needed).
     * Checks total balance across all product types.
     */
    public function getLowBalanceShops(int $threshold = 5): Collection
    {
        $shops = Shop::all();
        $lowBalance = collect();

        foreach ($shops as $shop) {
            $allBalances = $this->getShopAllBalances($shop);
            $totalBalance = array_sum($allBalances);
            if ($totalBalance <= $threshold && $totalBalance > 0) {
                $shop->total_balance = $totalBalance;
                $lowBalance->push($shop);
            }
        }

        return $lowBalance;
    }
}
