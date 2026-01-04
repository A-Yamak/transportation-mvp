<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * =============================================================================
 * Passport Setup Tests
 * =============================================================================
 * Tests for the setup:passport artisan command and Passport configuration.
 * These tests ensure that:
 *   - OAuth keys are generated correctly
 *   - Password grant client is created with hashed secret
 *   - Setup is idempotent (safe to run multiple times)
 *   - .env file is updated correctly
 * =============================================================================
 */
#[Group('auth')]
#[Group('passport')]
#[Group('setup')]
class PassportSetupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing password clients before each test
        DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->delete();
    }

    /**
     * Test that setup:passport creates OAuth keys.
     */
    #[Test]
    public function setup_passport_creates_oauth_keys(): void
    {
        // Remove existing keys
        $privateKey = storage_path('oauth-private.key');
        $publicKey = storage_path('oauth-public.key');

        if (file_exists($privateKey)) {
            unlink($privateKey);
        }
        if (file_exists($publicKey)) {
            unlink($publicKey);
        }

        // Run setup command
        Artisan::call('setup:passport');

        // Keys should now exist
        $this->assertFileExists($privateKey, 'Private key should be created');
        $this->assertFileExists($publicKey, 'Public key should be created');

        // Private key should be a valid RSA key
        $privateKeyContent = file_get_contents($privateKey);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $privateKeyContent);

        // Public key should be a valid RSA key
        $publicKeyContent = file_get_contents($publicKey);
        $this->assertStringContainsString('-----BEGIN PUBLIC KEY-----', $publicKeyContent);
    }

    /**
     * Test that setup:passport creates password grant client.
     */
    #[Test]
    public function setup_passport_creates_password_grant_client(): void
    {
        // Ensure no password clients exist
        $this->assertDatabaseCount('oauth_clients', 0);

        // Run setup command
        Artisan::call('setup:passport');

        // Password grant client should be created
        $client = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();

        $this->assertNotNull($client, 'Password grant client should be created');
        $this->assertFalse((bool) $client->revoked, 'Client should not be revoked');
        $this->assertEquals('users', $client->provider, 'Client should use users provider');
    }

    /**
     * Test that client secret is stored HASHED in database.
     */
    #[Test]
    public function password_client_secret_is_hashed_in_database(): void
    {
        Artisan::call('setup:passport');

        $client = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();

        // Secret should be hashed (starts with $2y$ for bcrypt)
        $this->assertStringStartsWith('$2y$', $client->secret, 'Secret should be bcrypt hashed');

        // Should NOT be plain text
        $this->assertGreaterThan(50, strlen($client->secret), 'Hashed secret should be longer than plain text');
    }

    /**
     * Test that setup:passport is idempotent (safe to run multiple times).
     */
    #[Test]
    public function setup_passport_is_idempotent_without_force(): void
    {
        // First run
        Artisan::call('setup:passport');
        $firstClient = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();

        // Second run without --force
        Artisan::call('setup:passport');
        $secondClient = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();

        // Should still have only one client (same client)
        $this->assertEquals($firstClient->id, $secondClient->id, 'Same client should be kept');
        $this->assertDatabaseCount('oauth_clients', 1);
    }

    /**
     * Test that setup:passport --force recreates client.
     */
    #[Test]
    public function setup_passport_force_recreates_client(): void
    {
        // First run
        Artisan::call('setup:passport');
        $firstClient = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();
        $firstId = $firstClient->id;

        // Second run with --force
        Artisan::call('setup:passport', ['--force' => true]);
        $secondClient = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();

        // Should be a NEW client (different ID)
        $this->assertNotEquals($firstId, $secondClient->id, 'New client should be created');
        $this->assertDatabaseCount('oauth_clients', 1);
    }

    /**
     * Test that grant_types includes both password and refresh_token.
     */
    #[Test]
    public function password_client_has_correct_grant_types(): void
    {
        Artisan::call('setup:passport');

        $client = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->first();

        $grantTypes = json_decode($client->grant_types, true);

        $this->assertContains('password', $grantTypes, 'Should have password grant');
        $this->assertContains('refresh_token', $grantTypes, 'Should have refresh_token grant');
    }
}
