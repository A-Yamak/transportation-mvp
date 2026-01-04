<?php

/**
 * =============================================================================
 * CORS (Cross-Origin Resource Sharing) Configuration
 * =============================================================================
 *
 * This configuration controls which origins can access your API.
 *
 * Development:
 *   Frontend: http://localhost:5173 (Vite dev server)
 *   Backend:  http://localhost:8000 (FrankenPHP/Octane)
 *
 * Production:
 *   Frontend: https://alsabiqoon.com
 *   Backend:  https://api.alsabiqoon.com
 *
 * =============================================================================
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Paths that should have CORS headers applied. Using a wildcard to cover
    | all API routes and the sanctum/csrf-cookie for SPA authentication.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'health',
        'ready',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | HTTP methods allowed for CORS requests. Using '*' to allow all methods.
    |
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origins allowed to make requests. In development, this includes localhost.
    | In production, this should be restricted to your actual domain.
    |
    | IMPORTANT: Don't use '*' in production with credentials!
    |
    */

    'allowed_origins' => [
        // Development
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',

        // Staging
        'https://staging.alsabiqoon.com',

        // Production
        'https://alsabiqoon.com',
        'https://www.alsabiqoon.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns to match against origins. Useful for wildcard subdomains.
    |
    */

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Headers that can be used in the actual request.
    |
    */

    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Headers exposed to the browser in the response.
    |
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the preflight response can be cached.
    |
    */

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Set to true to allow cookies/credentials in cross-origin requests.
    | Required for session-based authentication (Sanctum SPA).
    |
    */

    'supports_credentials' => true,

];
