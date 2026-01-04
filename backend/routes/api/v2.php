<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
|
| V2 API routes for future use. Routes are automatically prefixed
| with /api/v2 and use the 'api' middleware group.
|
| Controllers: App\Http\Controllers\Api\V2\*
| Requests:    App\Http\Requests\Api\V2\*
| Resources:   App\Http\Resources\Api\V2\*
|
| Status: Placeholder (Not yet active)
|
| When to create V2:
| - Breaking changes needed that can't be backwards compatible
| - Major restructuring of response format
| - Deprecating V1 endpoints
|
*/

// -----------------------------------------------------------------------------
// Public Routes
// -----------------------------------------------------------------------------

Route::get('/', fn () => response()->json([
    'version' => 'v2',
    'status' => 'planned',
    'message' => 'Al-Sabiqoon API V2 - Coming Soon',
]));

// -----------------------------------------------------------------------------
// V2 Routes will be added here when this version is activated
// -----------------------------------------------------------------------------
