<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            return $this->shouldRecord($entry);
        });
    }

    /**
     * Determine if the entry should be recorded based on recording mode.
     */
    protected function shouldRecord(IncomingEntry $entry): bool
    {
        // Always record everything in local environment
        if ($this->app->environment('local')) {
            return true;
        }

        // Always record monitored tags
        if ($entry->hasMonitoredTag()) {
            return true;
        }

        // Get recording mode settings
        $mode = config('dashboards.telescope.recording_mode', 'errors_only');
        $settings = config("dashboards.recording_modes.{$mode}", []);

        // Handle special cases first
        if ($entry->isReportableException()) {
            return $settings['exceptions'] ?? true;
        }

        if ($entry->isFailedRequest()) {
            return $settings['failed_requests'] ?? true;
        }

        if ($entry->isFailedJob()) {
            return $settings['failed_jobs'] ?? true;
        }

        if ($entry->isScheduledTask()) {
            return $settings['commands'] ?? false;
        }

        // Map entry types to settings keys
        $typeMap = [
            'exception' => 'exceptions',
            'request' => 'requests',
            'job' => 'jobs',
            'query' => 'queries',
            'model' => 'models',
            'event' => 'events',
            'mail' => 'mail',
            'notification' => 'notifications',
            'cache' => 'cache',
            'redis' => 'redis',
            'view' => 'views',
            'log' => 'logs',
            'command' => 'commands',
            'batch' => 'jobs',
            'client_request' => 'requests',
            'dump' => 'logs',
            'gate' => 'events',
            'schedule' => 'commands',
        ];

        $settingKey = $typeMap[$entry->type] ?? $entry->type;

        return $settings[$settingKey] ?? false;
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters([
            // CSRF tokens
            '_token',

            // Passwords
            'password',
            'password_confirmation',
            'current_password',
            'old_password',
            'new_password',

            // OTP and verification
            'otp',
            'verification_code',
            'verification_token',
            'pin',
            'pin_code',
            'two_factor_code',
            '2fa_code',

            // API keys and secrets
            'secret',
            'secret_key',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'bearer_token',
            'private_key',

            // Payment information
            'credit_card',
            'card_number',
            'card_cvc',
            'cvv',
            'cvc',
            'expiry_date',
            'card_expiry',

            // Personal identifiers
            'ssn',
            'social_security',
            'national_id',
            'passport_number',
        ]);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
            'x-api-key',
            'x-auth-token',
            'x-access-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            $authorizedEmails = array_filter(
                array_map('trim', explode(',', config('dashboards.authorized_emails', '')))
            );

            return in_array($user->email, $authorizedEmails);
        });
    }
}
