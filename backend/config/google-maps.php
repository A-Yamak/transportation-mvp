<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Google Maps API Key
    |--------------------------------------------------------------------------
    |
    | Your Google Maps API key with the following APIs enabled:
    | - Directions API (for route optimization)
    | - Distance Matrix API (for distance calculation)
    |
    | Get your key at: https://console.cloud.google.com/apis/credentials
    |
    */
    'api_key' => env('GOOGLE_MAPS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    |
    | Google Maps API endpoints
    |
    */
    'directions_url' => 'https://maps.googleapis.com/maps/api/directions/json',
    'distance_matrix_url' => 'https://maps.googleapis.com/maps/api/distancematrix/json',

    /*
    |--------------------------------------------------------------------------
    | Caching Strategy
    |--------------------------------------------------------------------------
    |
    | Cache Google Maps API responses to reduce costs and improve performance.
    | Routes rarely change for the same set of destinations.
    |
    */
    'cache' => [
        'enabled' => env('GOOGLE_MAPS_CACHE_ENABLED', true),
        'ttl' => env('GOOGLE_MAPS_CACHE_TTL', 900), // 15 minutes in seconds
        'prefix' => 'google_maps:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for Google Maps API requests
    |
    */
    'defaults' => [
        'mode' => 'driving', // driving, walking, bicycling, transit
        'avoid' => '', // tolls, highways, ferries
        'units' => 'metric', // metric or imperial
        'language' => 'en', // Language for responses (en, ar, etc.)
        'region' => 'jo', // Region bias (jo = Jordan)
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Start/End Point (Depot/Factory Location)
    |--------------------------------------------------------------------------
    |
    | The default starting point for route optimization.
    | This is typically the warehouse or factory location.
    | Default: Amman, Jordan (Melo factory area)
    |
    */
    'factory_location' => [
        'lat' => (float) env('GOOGLE_MAPS_FACTORY_LAT', 31.9539),
        'lng' => (float) env('GOOGLE_MAPS_FACTORY_LNG', 35.9106),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Optimization
    |--------------------------------------------------------------------------
    |
    | Settings for route optimization via Directions API
    |
    */
    'optimization' => [
        'max_waypoints' => 25, // Google Maps limit (free tier)
        'optimize_waypoints' => true, // Enable waypoint optimization
    ],

    /*
    |--------------------------------------------------------------------------
    | Distance Matrix
    |--------------------------------------------------------------------------
    |
    | Settings for distance calculations via Distance Matrix API
    |
    */
    'distance_matrix' => [
        'max_origins' => 25, // Google Maps limit
        'max_destinations' => 25, // Google Maps limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent exceeding Google Maps API rate limits
    |
    */
    'rate_limit' => [
        'enabled' => true,
        'max_requests_per_second' => 50, // Adjust based on your plan
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | How to handle Google Maps API errors
    |
    */
    'retry' => [
        'enabled' => env('GOOGLE_MAPS_RETRY_ENABLED', true),
        'max_attempts' => env('GOOGLE_MAPS_RETRY_ATTEMPTS', 3),
        'delay_ms' => env('GOOGLE_MAPS_RETRY_DELAY', 1000), // 1 second delay between retries
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds
    |
    */
    'timeout' => env('GOOGLE_MAPS_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Log API usage for cost monitoring
    | Directions API: $5 per 1,000 requests
    | Distance Matrix: $5 per 1,000 elements
    |
    */
    'cost_tracking' => [
        'enabled' => env('GOOGLE_MAPS_COST_TRACKING', false),
        'log_channel' => 'daily', // Laravel log channel
    ],
];
