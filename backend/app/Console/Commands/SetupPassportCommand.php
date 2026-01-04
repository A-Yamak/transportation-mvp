<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * =============================================================================
 * Setup Passport Command
 * =============================================================================
 * Creates Passport OAuth keys and password grant client.
 *
 * This command is idempotent - safe to run multiple times.
 *
 * How it works:
 *   - Creates OAuth keys if they don't exist
 *   - Creates a password grant client with HASHED secret in DB
 *   - Outputs plain secret for .env configuration
 *   - Writes to .env automatically in development
 *
 * For production:
 *   - Use env vars: PASSPORT_PASSWORD_CLIENT_ID, PASSPORT_PASSWORD_CLIENT_SECRET
 * =============================================================================
 */
class SetupPassportCommand extends Command
{
    protected $signature = 'setup:passport
        {--force : Recreate client even if one exists}';

    protected $description = 'Setup Passport OAuth keys and password grant client';

    public function handle(): int
    {
        $this->info('Setting up Laravel Passport...');

        // Step 1: Generate OAuth keys if needed
        $this->setupOAuthKeys();

        // Step 2: Create password grant client
        $this->setupPasswordClient();

        $this->newLine();
        $this->info('Passport setup complete!');

        return Command::SUCCESS;
    }

    protected function setupOAuthKeys(): void
    {
        $privateKey = storage_path('oauth-private.key');
        $publicKey = storage_path('oauth-public.key');

        if (file_exists($privateKey) && file_exists($publicKey)) {
            $this->line('  OAuth keys already exist.');
            return;
        }

        $this->line('  Generating OAuth keys...');
        $this->call('passport:keys', ['--force' => true]);
    }

    protected function setupPasswordClient(): void
    {
        // Check for existing password grant client with valid env vars
        $envClientId = env('PASSPORT_PASSWORD_CLIENT_ID');
        $envClientSecret = env('PASSPORT_PASSWORD_CLIENT_SECRET');

        if ($envClientId && $envClientSecret && !$this->option('force')) {
            // Verify client exists in DB
            $existing = DB::table('oauth_clients')->where('id', $envClientId)->first();
            if ($existing) {
                $this->line('  Password grant client already configured in .env');
                $this->line("  Client ID: {$envClientId}");
                return;
            }
        }

        // Check for existing client in DB
        $existing = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->where('revoked', false)
            ->first();

        if ($existing && !$this->option('force')) {
            $this->line('  Password grant client exists but not in .env.');
            $this->warn('  Run with --force to recreate, or add to .env:');
            $this->line("  PASSPORT_PASSWORD_CLIENT_ID={$existing->id}");
            $this->line("  PASSPORT_PASSWORD_CLIENT_SECRET=<secret from when client was created>");
            return;
        }

        // Delete existing password clients if --force
        if ($this->option('force')) {
            DB::table('oauth_clients')
                ->whereJsonContains('grant_types', 'password')
                ->delete();
            $this->line('  Deleted existing password client(s).');
        }

        // Create new client with HASHED secret (Passport requirement)
        $clientId = (string) Str::uuid();
        $plainSecret = Str::random(40);
        $hashedSecret = Hash::make($plainSecret);

        DB::table('oauth_clients')->insert([
            'id' => $clientId,
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Transportation App Password Client',
            'secret' => $hashedSecret,
            'provider' => 'users',
            'redirect_uris' => json_encode([config('app.url')]),
            'grant_types' => json_encode(['password', 'refresh_token']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Write to .env file for development
        $this->updateEnvFile($clientId, $plainSecret);

        $this->newLine();
        $this->info('  Password grant client created!');
        $this->newLine();
        $this->line("  Client ID: {$clientId}");
        $this->line("  Client Secret: {$plainSecret}");
        $this->newLine();

        if (app()->environment('local')) {
            $this->info('  Added to .env automatically.');
        } else {
            $this->warn('  Add to .env:');
            $this->line("  PASSPORT_PASSWORD_CLIENT_ID={$clientId}");
            $this->line("  PASSPORT_PASSWORD_CLIENT_SECRET={$plainSecret}");
        }
    }

    protected function updateEnvFile(string $clientId, string $plainSecret): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);

        // Update or add PASSPORT_PASSWORD_CLIENT_ID
        if (preg_match('/^PASSPORT_PASSWORD_CLIENT_ID=.*/m', $envContent)) {
            $envContent = preg_replace(
                '/^PASSPORT_PASSWORD_CLIENT_ID=.*/m',
                "PASSPORT_PASSWORD_CLIENT_ID={$clientId}",
                $envContent
            );
        } else {
            $envContent .= "\nPASSPORT_PASSWORD_CLIENT_ID={$clientId}";
        }

        // Update or add PASSPORT_PASSWORD_CLIENT_SECRET
        if (preg_match('/^PASSPORT_PASSWORD_CLIENT_SECRET=.*/m', $envContent)) {
            $envContent = preg_replace(
                '/^PASSPORT_PASSWORD_CLIENT_SECRET=.*/m',
                "PASSPORT_PASSWORD_CLIENT_SECRET={$plainSecret}",
                $envContent
            );
        } else {
            $envContent .= "\nPASSPORT_PASSWORD_CLIENT_SECRET={$plainSecret}";
        }

        file_put_contents($envPath, $envContent);
    }
}
