<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Price Per Kilometer
    |--------------------------------------------------------------------------
    |
    | This is the fallback price per kilometer when no pricing tier is found
    | for a specific business type. Typically used for new business types or
    | when pricing tiers haven't been configured yet.
    |
    */
    'default_price_per_km' => (float) env('DEFAULT_PRICE_PER_KM', 0.50),

    /*
    |--------------------------------------------------------------------------
    | Minimum Trip Cost
    |--------------------------------------------------------------------------
    |
    | The minimum cost for any delivery, regardless of distance.
    | Ensures profitability for very short trips.
    |
    */
    'minimum_trip_cost' => (float) env('MINIMUM_TRIP_COST', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Base Fee
    |--------------------------------------------------------------------------
    |
    | Fixed fee added to every delivery in addition to distance-based pricing.
    | Set to 0 if you only want distance-based pricing.
    |
    */
    'base_fee' => (float) env('BASE_FEE', 0.00),

    /*
    |--------------------------------------------------------------------------
    | Pricing Tiers by Business Type
    |--------------------------------------------------------------------------
    |
    | Default pricing tiers for different business types.
    | These can be overridden by database pricing tiers.
    |
    */
    'business_type_rates' => [
        'bulk_order' => [
            'price_per_km' => 0.45,
            'base_fee' => 2.00,
            'minimum_cost' => 10.00,
        ],
        'pickup' => [
            'price_per_km' => 0.60,
            'base_fee' => 5.00,
            'minimum_cost' => 15.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Distance-Based Tier Discounts
    |--------------------------------------------------------------------------
    |
    | Apply discounts for longer trips to encourage bulk usage.
    | Format: ['min_km' => discount_percentage]
    |
    */
    'distance_discounts' => [
        50 => 0.05,  // 5% discount for trips over 50 KM
        100 => 0.10, // 10% discount for trips over 100 KM
        200 => 0.15, // 15% discount for trips over 200 KM
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | Tax rate to apply to delivery costs (as decimal).
    | Set to 0 if tax is not applicable or handled separately.
    |
    */
    'tax_rate' => (float) env('TAX_RATE', 0.00),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for pricing display and calculations.
    |
    */
    'currency' => env('CURRENCY', 'JOD'), // Jordanian Dinar

    /*
    |--------------------------------------------------------------------------
    | Currency Symbol
    |--------------------------------------------------------------------------
    |
    | Symbol to display with pricing.
    |
    */
    'currency_symbol' => env('CURRENCY_SYMBOL', 'JOD'),

    /*
    |--------------------------------------------------------------------------
    | Rounding Precision
    |--------------------------------------------------------------------------
    |
    | Number of decimal places to round prices to.
    |
    */
    'rounding_precision' => 2,
];
