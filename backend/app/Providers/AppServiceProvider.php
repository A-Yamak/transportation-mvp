<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
    }

    /**
     * Configure Laravel Passport token expiration.
     */
    protected function configurePassport(): void
    {
        // Enable Password Grant (required for OAuth2 password flow)
        Passport::enablePasswordGrant();

        // Access tokens expire in 1 hour (or configured value)
        Passport::tokensExpireIn(
            now()->addMinutes((int) env('PASSPORT_TOKEN_EXPIRATION', 60))
        );

        // Refresh tokens expire in 7 days (or configured value)
        Passport::refreshTokensExpireIn(
            now()->addMinutes((int) env('PASSPORT_REFRESH_TOKEN_EXPIRATION', 10080))
        );

        // Personal access tokens expire in 6 months
        Passport::personalAccessTokensExpireIn(
            now()->addMonths(6)
        );
    }
}
