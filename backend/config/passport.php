<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Password Grant Client
    |--------------------------------------------------------------------------
    |
    | The Password Grant client credentials used for first-party API
    | authentication. These are stored securely on the server and used
    | by the AuthController to obtain tokens on behalf of users.
    |
    | Generate with: php artisan passport:client --password
    |
    */

    'password_client' => [
        'id' => env('PASSPORT_PASSWORD_CLIENT_ID'),
        'secret' => env('PASSPORT_PASSWORD_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the expiration times for access tokens and
    | refresh tokens. These are configured in AppServiceProvider.
    |
    | Defaults:
    |   - Access token: 60 minutes (1 hour)
    |   - Refresh token: 10080 minutes (7 days)
    |
    */

    'token_expiration' => (int) env('PASSPORT_TOKEN_EXPIRATION', 60),
    'refresh_token_expiration' => (int) env('PASSPORT_REFRESH_TOKEN_EXPIRATION', 10080),

];
