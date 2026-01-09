<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // ---------------------------------------------------------------------
            // Versioned API Routes
            // ---------------------------------------------------------------------
            // Each API version has its own route file and prefix.
            // This allows for:
            //   - Independent versioning of API endpoints
            //   - Gradual deprecation of old versions
            //   - Clean separation of concerns
            //
            // URL Structure:
            //   - /api/v1/* -> routes/api/v1.php (Active)
            //   - /api/v2/* -> routes/api/v2.php (Planned)
            //   - /api/v3/* -> routes/api/v3.php (Future)
            // ---------------------------------------------------------------------

            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(base_path('routes/api/v1.php'));

            Route::middleware('api')
                ->prefix('api/v2')
                ->name('api.v2.')
                ->group(base_path('routes/api/v2.php'));

            Route::middleware('api')
                ->prefix('api/v3')
                ->name('api.v3.')
                ->group(base_path('routes/api/v3.php'));

            // External B2B Integration API
            Route::middleware('api')
                ->prefix('api/external/v1')
                ->name('api.external.v1.')
                ->group(base_path('routes/api/external/v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware aliases
        $middleware->alias([
            'auth.api_key' => \App\Http\Middleware\AuthenticateBusinessApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
