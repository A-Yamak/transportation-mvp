<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V3 Routes
|--------------------------------------------------------------------------
|
| V3 API routes for future use. Routes are automatically prefixed
| with /api/v3 and use the 'api' middleware group.
|
| Controllers: App\Http\Controllers\Api\V3\*
| Requests:    App\Http\Requests\Api\V3\*
| Resources:   App\Http\Resources\Api\V3\*
|
| Status: Placeholder (Not yet active)
|
| When to create V3:
| - Major platform overhaul
| - New authentication mechanisms
| - Significant API redesign
|
*/

// -----------------------------------------------------------------------------
// Public Routes
// -----------------------------------------------------------------------------

Route::get('/', fn () => response()->json([
    'version' => 'v3',
    'status' => 'planned',
    'message' => 'Al-Sabiqoon API V3 - Future Release',
]));

// -----------------------------------------------------------------------------
// V3 Routes will be added here when this version is activated
// -----------------------------------------------------------------------------
