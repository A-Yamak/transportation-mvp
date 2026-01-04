<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * =============================================================================
 * User Endpoint Feature Tests
 * =============================================================================
 * Tests for getting authenticated user data.
 *
 * @group auth
 * @group user
 * =============================================================================
 */
class UserTest extends TestCase
{
    /**
     * Test authenticated user can get their data.
     */
    public function test_authenticated_user_can_get_their_data(): void
    {
        $user = $this->createAuthenticatedUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com');
    }

    /**
     * Test unauthenticated user cannot get user data.
     */
    public function test_unauthenticated_user_cannot_get_user_data(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $this->assertUnauthorized($response);
    }

    /**
     * Test user data includes email verification status.
     */
    public function test_user_data_includes_verification_status(): void
    {
        $user = $this->createAuthenticatedUser([
            'email_verified_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data['email_verified_at']);
    }

    /**
     * Test user data shows null for unverified email.
     */
    public function test_user_data_shows_null_for_unverified_email(): void
    {
        $user = $this->createAuthenticatedUser([
            'email_verified_at' => null,
        ]);

        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(200)
            ->assertJsonPath('data.email_verified_at', null);
    }
}
