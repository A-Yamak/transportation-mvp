<?php

declare(strict_types=1);

use App\Http\Controllers\Api\External\V1\ShopController;
use App\Http\Controllers\Api\External\V1\WasteCollectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External API V1 Routes
|--------------------------------------------------------------------------
|
| B2B Integration endpoints for external business systems (e.g., melo-erp).
| Authenticated via X-API-Key header.
|
| Prefix: /api/external/v1
| Middleware: auth.api_key
*/

Route::middleware('auth.api_key')->group(function () {
    // ========== SHOP MANAGEMENT ==========
    Route::prefix('shops')->controller(ShopController::class)->group(function () {
        Route::post('sync', 'sync')->name('shops.sync');
        Route::get('/', 'index')->name('shops.index');
        Route::get('{externalShopId}', 'show')->name('shops.show');
        Route::put('{externalShopId}', 'update')->name('shops.update');
        Route::delete('{externalShopId}', 'destroy')->name('shops.destroy');
    });

    // ========== WASTE COLLECTION ==========
    Route::prefix('waste')->controller(WasteCollectionController::class)->group(function () {
        Route::get('expected', 'expected')->name('waste.expected');
        Route::post('expected', 'setExpected')->name('waste.setExpected');
    });
});
