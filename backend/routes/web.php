<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| These endpoints are used by Kubernetes probes to determine the health
| of the application. They should NOT require authentication.
|
| /up     - Laravel's built-in startup check (used by startup probe)
| /health - Liveness check (is the app alive?)
| /ready  - Readiness check (can the app accept traffic?)
|
| Kubernetes Probe Configuration:
| --------------------------------
| startupProbe:   GET /up     (allow longer startup time)
| livenessProbe:  GET /health (restart if unhealthy)
| readinessProbe: GET /ready  (remove from LB if not ready)
|
*/

Route::get('/health', [HealthController::class, 'health'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');
