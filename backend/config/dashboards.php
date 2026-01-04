<?php

/**
 * =============================================================================
 * Dashboard Authorization & Recording Configuration
 * =============================================================================
 *
 * Centralized configuration for Laravel Telescope and Horizon dashboards.
 * Controls who can access dashboards and what data gets recorded.
 *
 * Environment Variable Recommendations:
 * -------------------------------------
 * Local:      TELESCOPE_RECORDING_MODE=all
 * Staging:    TELESCOPE_RECORDING_MODE=all
 * Production: TELESCOPE_RECORDING_MODE=errors_only
 *
 * =============================================================================
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Authorized Dashboard Emails
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of emails allowed to access Telescope & Horizon.
    | These users must be authenticated before accessing dashboards.
    |
    | Example: "admin@alsabiqoon.com,dev@alsabiqoon.com"
    |
    */

    'authorized_emails' => env('DASHBOARD_AUTHORIZED_EMAILS', ''),

    /*
    |--------------------------------------------------------------------------
    | Telescope Configuration
    |--------------------------------------------------------------------------
    |
    | enabled:        Master switch for Telescope data recording
    | prune_hours:    How long to keep entries before auto-deletion
    | recording_mode: What to record (all, errors_only, important_only)
    |
    */

    'telescope' => [
        'enabled' => env('TELESCOPE_ENABLED', true),
        'prune_hours' => env('TELESCOPE_PRUNE_HOURS', 48),
        'recording_mode' => env('TELESCOPE_RECORDING_MODE', 'all'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Configuration
    |--------------------------------------------------------------------------
    */

    'horizon' => [
        'enabled' => env('HORIZON_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording Mode Definitions
    |--------------------------------------------------------------------------
    |
    | Defines what each recording mode captures. These are used by
    | TelescopeServiceProvider to filter entries in non-local environments.
    |
    | Modes:
    |   - all:            Everything (development/staging)
    |   - errors_only:    Exceptions, failed requests/jobs, logs, mail (production)
    |   - important_only: Critical errors only (high-traffic production)
    |
    */

    'recording_modes' => [

        // Everything - for development and staging
        'all' => [
            'exceptions' => true,
            'failed_requests' => true,
            'failed_jobs' => true,
            'slow_queries' => true,
            'logs' => true,
            'queries' => true,
            'models' => true,
            'events' => true,
            'mail' => true,
            'notifications' => true,
            'cache' => true,
            'redis' => true,
            'views' => true,
            'requests' => true,
            'commands' => true,
            'jobs' => true,
        ],

        // Errors and important events only - recommended for production
        'errors_only' => [
            'exceptions' => true,
            'failed_requests' => true,
            'failed_jobs' => true,
            'slow_queries' => true,
            'logs' => true,
            'queries' => false,
            'models' => false,
            'events' => false,
            'mail' => true,
            'notifications' => true,
            'cache' => false,
            'redis' => false,
            'views' => false,
            'requests' => false,
            'commands' => false,
            'jobs' => false,
        ],

        // Critical errors only - minimal logging for high-traffic production
        'important_only' => [
            'exceptions' => true,
            'failed_requests' => true,
            'failed_jobs' => true,
            'slow_queries' => false,
            'logs' => true,
            'queries' => false,
            'models' => false,
            'events' => false,
            'mail' => false,
            'notifications' => false,
            'cache' => false,
            'redis' => false,
            'views' => false,
            'requests' => false,
            'commands' => false,
            'jobs' => false,
        ],
    ],
];
