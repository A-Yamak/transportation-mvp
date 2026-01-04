<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\PassportTestHelper;

/**
 * =============================================================================
 * Login Feature Tests (Real OAuth)
 * =============================================================================
 * These tests hit the REAL /oauth/token endpoint.
 * They verify actual OAuth flow, not just validation logic.
 *
 * Benefits of real OAuth testing:
 *   - Catches client secret hashing mismatches
 *   - Verifies token generation works end-to-end
 *   - Tests actual JWT format and structure
 *   - No false positives from mocking
 * =============================================================================
 */
#[Group('auth')]
#[Group('login')]
class LoginTest extends TestCase
{
    use PassportTestHelper;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set up Passport client for real OAuth testing
        $this->setUpPassportClient();
    }

    /**
     * Test successful user login with real OAuth.
     */
    #[Test]
    public function user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'expires_in',
                'token_type',
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                'message',
            ])
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('message', 'Login successful');

        // Verify it's a real JWT (not a mocked token)
        $this->assertValidJwt($response->json('access_token'));
    }

    /**
     * Test login fails with wrong password.
     */
    #[Test]
    public function login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test login fails with non-existent email.
     */
    #[Test]
    public function login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test login fails without email.
     */
    #[Test]
    public function login_fails_without_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test login fails without password.
     */
    #[Test]
    public function login_fails_without_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
        ]);

        $this->assertValidationError($response, ['password']);
    }

    /**
     * Test login fails with invalid email format.
     */
    #[Test]
    public function login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test login returns complete user data.
     */
    #[Test]
    public function login_returns_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'John Doe')
            ->assertJsonPath('user.email', $user->email);
    }

    /**
     * Test that the access token can be used to authenticate requests.
     */
    #[Test]
    public function access_token_can_authenticate_requests(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('access_token');

        // Use the token to access a protected endpoint
        $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
