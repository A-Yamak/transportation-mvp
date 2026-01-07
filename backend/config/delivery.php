<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Auto-Assignment Settings
    |--------------------------------------------------------------------------
    |
    | When enabled, new delivery requests will be automatically assigned to
    | an available driver. This is ideal for single-driver operations.
    |
    */

    'auto_assign' => [
        'enabled' => env('DELIVERY_AUTO_ASSIGN', true),

        // Strategy: 'single' (always same driver) or 'round_robin' (rotate between drivers)
        'strategy' => env('DELIVERY_AUTO_ASSIGN_STRATEGY', 'single'),

        // Specific driver ID to always assign to (optional, for 'single' strategy)
        // If null, will use the first active driver
        'driver_id' => env('DELIVERY_AUTO_ASSIGN_DRIVER_ID', null),
    ],
];
