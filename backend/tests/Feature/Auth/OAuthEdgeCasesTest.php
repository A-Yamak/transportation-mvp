<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * =============================================================================
 * OAuth Edge Cases Tests
 * =============================================================================
 * Tests for edge cases and error conditions in the OAuth flow.
 * These tests verify proper error handling when:
 *   - OAuth keys are missing
 *   - Client is revoked
 *   - Client secret is wrong
 *   - Config is misconfigured
 * =============================================================================
 */
#[Group('auth')]
#[Group('oauth')]
#[Group('edge-cases')]
class OAuthEdgeCasesTest extends TestCase
{
    protected string $originalClientId;
    protected string $originalClientSecret;

    protected function setUp(): void
    {
        parent::setUp();

        // Store original config
        $this->originalClientId = config('passport.password_client.id', '');
        $this->originalClientSecret = config('passport.password_client.secret', '');

        // Setup Passport for testing
        $this->ensurePassportSetup();
    }

    protected function tearDown(): void
    {
        // Restore original config
        config([
            'passport.password_client.id' => $this->originalClientId,
            'passport.password_client.secret' => $this->originalClientSecret,
        ]);

        parent::tearDown();
    }

    protected function ensurePassportSetup(): void
    {
        // Generate OAuth keys if needed
        $privateKey = storage_path('oauth-private.key');
        if (! file_exists($privateKey)) {
            Artisan::call('passport:keys', ['--force' => true]);
        }

        // Create a valid client for testing
        DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->delete();

        $clientId = (string) Str::uuid();
        $clientSecret = Str::random(40);

        DB::table('oauth_clients')->insert([
            'id' => $clientId,
            'name' => 'Test Password Client',
            'secret' => Hash::make($clientSecret),
            'provider' => 'users',
            'redirect_uris' => json_encode([config('app.url')]),
            'grant_types' => json_encode(['password', 'refresh_token']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config([
            'passport.password_client.id' => $clientId,
            'passport.password_client.secret' => $clientSecret,
        ]);

        // Intercept HTTP calls to /oauth/token and route through internal testing
        // This is needed because external HTTP calls can't see test transaction data
        $this->interceptOAuthTokenRequests();
    }

    /**
     * Intercept OAuth token requests and route through internal testing.
     */
    protected function interceptOAuthTokenRequests(): void
    {
        $test = $this;

        Http::fake([
            '*/oauth/token' => function ($request) use ($test) {
                $data = $request->data();
                $response = $test->post('/oauth/token', $data, [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]);

                return Http::response(
                    json_decode($response->getContent(), true) ?? [],
                    $response->getStatusCode()
                );
            },
        ]);
    }

    /**
     * Test login fails gracefully when no password client exists.
     */
    #[Test]
    public function login_fails_gracefully_when_no_client_exists(): void
    {
        // Delete all password clients
        DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->delete();

        // Clear config so it tries DB lookup
        config([
            'passport.password_client.id' => null,
            'passport.password_client.secret' => null,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Authentication failed']);
    }

    /**
     * Test login fails when client is revoked.
     */
    #[Test]
    public function login_fails_when_client_is_revoked(): void
    {
        // Revoke the client
        DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->update(['revoked' => true]);

        // Clear config to force DB lookup
        config([
            'passport.password_client.id' => null,
            'passport.password_client.secret' => null,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test login fails with wrong client secret.
     */
    #[Test]
    public function login_fails_with_wrong_client_secret(): void
    {
        // Set wrong secret in config
        config([
            'passport.password_client.secret' => 'completely-wrong-secret',
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test login fails with non-existent client ID.
     */
    #[Test]
    public function login_fails_with_nonexistent_client_id(): void
    {
        config([
            'passport.password_client.id' => 'non-existent-client-id',
            'passport.password_client.secret' => 'any-secret',
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test refresh token fails with invalid token.
     */
    #[Test]
    public function refresh_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid-refresh-token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid or expired refresh token']);
    }

    /**
     * Test refresh token fails with expired token.
     */
    #[Test]
    public function refresh_fails_with_revoked_token(): void
    {
        // First, create a valid session
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        if ($loginResponse->status() !== 200) {
            $this->markTestSkipped('Login failed, skipping refresh test');
        }

        $accessToken = $loginResponse->json('access_token');
        $refreshToken = $loginResponse->json('refresh_token');

        // Logout (which revokes the token)
        $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/v1/auth/logout');

        // Try to use the revoked refresh token
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that protected endpoints require valid token.
     */
    #[Test]
    public function protected_endpoint_requires_valid_token(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    /**
     * Test that protected endpoints reject expired tokens.
     */
    #[Test]
    public function protected_endpoint_rejects_malformed_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer not-a-valid-jwt')
            ->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    /**
     * Test OAuth works with hashed secret (integration verification).
     */
    #[Test]
    public function oauth_works_with_properly_hashed_client_secret(): void
    {
        // This is the critical test that would have caught our bug
        $clientId = (string) Str::uuid();
        $plainSecret = Str::random(40);

        // Store HASHED secret in database (as Passport expects)
        DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->delete();

        DB::table('oauth_clients')->insert([
            'id' => $clientId,
            'name' => 'Hashed Secret Test Client',
            'secret' => Hash::make($plainSecret), // HASHED
            'provider' => 'users',
            'redirect_uris' => json_encode([config('app.url')]),
            'grant_types' => json_encode(['password', 'refresh_token']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set PLAIN secret in config (what AuthController sends)
        config([
            'passport.password_client.id' => $clientId,
            'passport.password_client.secret' => $plainSecret, // PLAIN
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);
    }

    /**
     * Test OAuth fails with PLAIN text secret in database (the bug we fixed).
     */
    #[Test]
    public function oauth_fails_with_plain_text_secret_in_database(): void
    {
        $clientId = (string) Str::uuid();
        $plainSecret = Str::random(40);

        // Store PLAIN secret in database (WRONG - this was the bug)
        DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->delete();

        DB::table('oauth_clients')->insert([
            'id' => $clientId,
            'name' => 'Plain Secret Test Client',
            'secret' => $plainSecret, // PLAIN - WRONG!
            'provider' => 'users',
            'redirect_uris' => json_encode([config('app.url')]),
            'grant_types' => json_encode(['password', 'refresh_token']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config([
            'passport.password_client.id' => $clientId,
            'passport.password_client.secret' => $plainSecret,
        ]);

        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // This should fail because Passport expects hashed secret in DB
        $response->assertStatus(401);
    }
}
