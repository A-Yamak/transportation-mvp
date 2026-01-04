<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * =============================================================================
 * Logout Feature Tests
 * =============================================================================
 * Tests for user logout endpoint.
 *
 * @group auth
 * @group logout
 * =============================================================================
 */
class LogoutTest extends TestCase
{
    /**
     * Test authenticated user can logout.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);
    }

    /**
     * Test unauthenticated user cannot logout.
     */
    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $this->assertUnauthorized($response);
    }
}
