<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Business;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

/**
 * Shop Sync Service
 *
 * Handles synchronization of shop data from external business systems.
 * Performs upsert operations (insert or update) based on external_shop_id.
 */
class ShopSyncService
{
    /**
     * Sync multiple shops for a business.
     *
     * @param  Business  $business
     * @param  array<array-key, array>  $shopsData
     * @param  array{handle_deletions?: bool}  $options
     * @return array{created: int, updated: int, deleted: int, total: int}
     */
    public function syncShops(
        Business $business,
        array $shopsData,
        array $options = []
    ): array {
        $created = 0;
        $updated = 0;
        $total = count($shopsData);

        foreach ($shopsData as $shopData) {
            try {
                $shop = Shop::updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'external_shop_id' => $shopData['id'],
                    ],
                    $this->prepareShopData($business, $shopData)
                );

                $shop->wasRecentlyCreated ? $created++ : $updated++;

                Log::debug('Shop synced', [
                    'action' => $shop->wasRecentlyCreated ? 'created' : 'updated',
                    'shop_id' => $shop->id,
                    'external_shop_id' => $shopData['id'],
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to sync shop', [
                    'external_shop_id' => $shopData['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Handle deletions if requested
        $deleted = 0;
        if ($options['handle_deletions'] ?? true) {
            $externalIds = collect($shopsData)->pluck('id')->toArray();
            $deleted = Shop::where('business_id', $business->id)
                ->whereNotIn('external_shop_id', $externalIds)
                ->update(['is_active' => false]);

            if ($deleted > 0) {
                Log::info('Shops deactivated', [
                    'business_id' => $business->id,
                    'count' => $deleted,
                ]);
            }
        }

        // Update sync timestamp
        $business->update([
            'last_shop_sync_at' => now(),
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'total' => $total,
        ];
    }

    /**
     * Find and link shop to a destination.
     *
     * @param  Business  $business
     * @param  string  $externalShopId
     * @return Shop|null
     */
    public function findAndLinkShop(Business $business, string $externalShopId): ?Shop
    {
        return Shop::where('business_id', $business->id)
            ->where('external_shop_id', $externalShopId)
            ->active()
            ->first();
    }

    /**
     * Prepare shop data for create/update operations.
     *
     * @param  Business  $business
     * @param  array  $data
     * @return array
     */
    protected function prepareShopData(Business $business, array $data): array
    {
        return [
            'business_id' => $business->id,
            'external_shop_id' => $data['id'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'lat' => $data['latitude'] ?? null,
            'lng' => $data['longitude'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_number'] ?? null,
            'track_waste' => $data['track_waste'] ?? false,
            'is_active' => ($data['status'] ?? 'active') === 'active',
            'last_synced_at' => now(),
            'sync_metadata' => $data['metadata'] ?? null,
        ];
    }

    /**
     * Set expected waste dates for shops.
     *
     * @param  Business  $business
     * @param  array<array-key, array{external_shop_id: string, expected_waste_date: string}>  $shopsData
     * @return int Updated shop count
     */
    public function setExpectedWasteDates(Business $business, array $shopsData): int
    {
        $updated = 0;

        foreach ($shopsData as $shopData) {
            $updated += Shop::where('business_id', $business->id)
                ->where('external_shop_id', $shopData['external_shop_id'])
                ->update([
                    'track_waste' => true,
                    'expected_waste_date' => $shopData['expected_waste_date'],
                ]);
        }

        Log::info('Expected waste dates updated', [
            'business_id' => $business->id,
            'updated_count' => $updated,
        ]);

        return $updated;
    }
}
