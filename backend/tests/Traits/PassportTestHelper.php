<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * =============================================================================
 * Passport Test Helper
 * =============================================================================
 * Centralized Passport OAuth setup for all authentication tests.
 *
 * WHY THIS TRAIT EXISTS:
 * - Tests need to hit real Passport /oauth/token endpoint
 * - But external HTTP calls can't see test transaction data
 * - Solution: Intercept HTTP calls and route through Laravel's internal testing
 * - This tests REAL Passport behavior while staying in same DB transaction
 *
 * USAGE:
 *   class LoginTest extends TestCase
 *   {
 *       use PassportTestHelper;
 *
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->setUpPassportClient();
 *       }
 *   }
 * =============================================================================
 */
trait PassportTestHelper
{
    /**
     * Cached client credentials (shared across tests in same run).
     */
    protected static ?string $testClientId = null;

    protected static ?string $testClientSecret = null;

    /**
     * Set up the Passport password grant client for testing.
     *
     * This method:
     * 1. Creates or reuses a Passport client
     * 2. Sets config for AuthController to use
     * 3. Intercepts /oauth/token calls to use internal routing
     */
    protected function setUpPassportClient(): void
    {
        // Always create a fresh client in each test to avoid transaction issues
        $this->createTestPasswordClient();
        $this->configurePassportClient();
        $this->interceptOAuthTokenRequests();
    }

    /**
     * Create a test password grant client.
     *
     * Creates a new client for each test to ensure isolation.
     * The secret is HASHED in the database (as Passport expects).
     */
    protected function createTestPasswordClient(): void
    {
        self::$testClientId = (string) Str::uuid();
        self::$testClientSecret = Str::random(40);

        DB::table('oauth_clients')->insert([
            'id' => self::$testClientId,
            'name' => 'Test Password Client ' . self::$testClientId,
            'secret' => Hash::make(self::$testClientSecret), // HASHED in DB
            'provider' => 'users',
            'redirect_uris' => json_encode([config('app.url')]),
            'grant_types' => json_encode(['password', 'refresh_token']),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Configure the application to use the test client.
     */
    protected function configurePassportClient(): void
    {
        config([
            'passport.password_client.id' => self::$testClientId,
            'passport.password_client.secret' => self::$testClientSecret, // PLAIN for requests
        ]);
    }

    /**
     * Intercept OAuth token requests and route through internal testing.
     *
     * WHY THIS IS NEEDED:
     * - AuthController uses Http::post() to call /oauth/token
     * - This makes a REAL HTTP request to the running backend service
     * - The running service has a DIFFERENT database connection
     * - It cannot see data in the test's transaction
     *
     * SOLUTION:
     * - Intercept HTTP calls matching /oauth/token
     * - Route them through Laravel's internal test client ($this->post())
     * - This keeps everything in the same process and transaction
     * - We still test REAL Passport behavior, just without external HTTP
     */
    protected function interceptOAuthTokenRequests(): void
    {
        $test = $this;

        Http::fake([
            '*/oauth/token' => function ($request) use ($test) {
                // Get the form data from the request
                $data = $request->data();

                // Route through Laravel's internal test client
                // Use asForm() style - post with form data
                $response = $test->post('/oauth/token', $data, [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]);

                $content = json_decode($response->getContent(), true);

                return Http::response(
                    $content ?? [],
                    $response->getStatusCode()
                );
            },
        ]);
    }

    /**
     * Assert that a token response contains a valid JWT.
     *
     * @param  string  $token  The access token to validate
     */
    protected function assertValidJwt(string $token): void
    {
        $this->assertStringStartsWith('eyJ', $token, 'Access token should be a valid JWT');
        $this->assertCount(3, explode('.', $token), 'JWT should have 3 parts (header.payload.signature)');
    }
}
