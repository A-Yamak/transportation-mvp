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
 * Registration Feature Tests (Real OAuth)
 * =============================================================================
 * These tests hit the REAL /oauth/token endpoint.
 * They verify actual OAuth flow for new user registration.
 *
 * Benefits of real OAuth testing:
 *   - Catches client secret hashing mismatches
 *   - Verifies token generation works end-to-end
 *   - Tests actual JWT format and structure
 *   - No false positives from mocking
 * =============================================================================
 */
#[Group('auth')]
#[Group('registration')]
class RegistrationTest extends TestCase
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
     * Test successful user registration with real OAuth.
     */
    #[Test]
    public function user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'register_' . uniqid() . '@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'expires_in',
                'token_type',
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                'message',
            ])
            ->assertJsonPath('user.name', 'John Doe')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('message', 'Registration successful');

        // Verify it's a real JWT (not a mocked token)
        $this->assertValidJwt($response->json('access_token'));

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => $response->json('user.email'),
        ]);
    }

    /**
     * Test registration fails with missing name.
     */
    #[Test]
    public function registration_fails_without_name(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertValidationError($response, ['name']);
    }

    /**
     * Test registration fails with missing email.
     */
    #[Test]
    public function registration_fails_without_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test registration fails with invalid email format.
     */
    #[Test]
    public function registration_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test registration fails with duplicate email.
     */
    #[Test]
    public function registration_fails_with_duplicate_email(): void
    {
        $existingUser = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => $existingUser->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test registration fails without password.
     */
    #[Test]
    public function registration_fails_without_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertValidationError($response, ['password']);
    }

    /**
     * Test registration fails with password mismatch.
     */
    #[Test]
    public function registration_fails_with_password_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $this->assertValidationError($response, ['password']);
    }

    /**
     * Test password is properly hashed in database.
     */
    #[Test]
    public function password_is_hashed_in_database(): void
    {
        $email = 'hash_test_' . uniqid() . '@example.com';

        $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', $email)->first();

        $this->assertNotNull($user);
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Test that the access token can be used to authenticate requests.
     */
    #[Test]
    public function registration_token_can_authenticate_requests(): void
    {
        $email = 'auth_test_' . uniqid() . '@example.com';

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $accessToken = $registerResponse->json('access_token');

        // Use the token to access a protected endpoint
        $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', $email);
    }
}
