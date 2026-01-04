<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| All V1 API routes are defined here. Routes are automatically prefixed
| with /api/v1 and use the 'api' middleware group.
|
| Controllers: App\Http\Controllers\Api\V1\*
| Requests:    App\Http\Requests\Api\V1\*
| Resources:   App\Http\Resources\Api\V1\*
|
| Status: Active (Current Version)
|
*/

// -----------------------------------------------------------------------------
// Public Routes (No Authentication Required)
// -----------------------------------------------------------------------------

Route::get('/', fn () => response()->json([
    'version' => 'v1',
    'status' => 'active',
    'message' => 'Al-Sabiqoon API V1',
]));

// Health check (useful for load balancers)
Route::get('/health', fn () => response()->json(['status' => 'healthy']));

// -----------------------------------------------------------------------------
// Authentication Routes
// -----------------------------------------------------------------------------

Route::prefix('auth')->group(function () {
    // Public auth routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Protected auth routes
    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});

// -----------------------------------------------------------------------------
// Protected Routes (Authentication Required)
// -----------------------------------------------------------------------------

Route::middleware('auth:api')->group(function () {
    // TODO: Add V1 resource routes here
    // Example:
    // Route::apiResource('users', UserController::class);
    // Route::apiResource('projects', ProjectController::class);
});
