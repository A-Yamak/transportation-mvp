<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;

/**
 * =============================================================================
 * Transportation App - Base TestCase
 * =============================================================================
 * Base class for all test cases. Provides common testing utilities and traits.
 *
 * Features:
 *   - RefreshDatabase: Resets database between tests
 *   - WithFaker: Provides fake data generation
 *   - Authentication helpers
 *   - API response assertions
 *
 * DEBUG MODE:
 *   Set PERSIST_TEST_DATA=true in phpunit.xml to keep data after tests.
 *   This lets you view test data in Sequel Ace for learning/debugging.
 *
 *   When done learning, set PERSIST_TEST_DATA=false for proper test isolation.
 * =============================================================================
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Default headers for API requests.
     *
     * @var array<string, string>
     */
    protected $defaultHeaders = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set default headers for all requests
        $this->withHeaders($this->defaultHeaders);

        // Ensure Passport OAuth keys exist
        $this->ensurePassportKeysExist();
    }

    /**
     * Ensure Passport OAuth keys exist for token generation.
     *
     * This prevents "Key path does not exist" errors during tests.
     * Keys are only generated if missing (cached across test runs).
     */
    protected function ensurePassportKeysExist(): void
    {
        $privateKey = storage_path('oauth-private.key');
        if (! file_exists($privateKey)) {
            Artisan::call('passport:keys', ['--force' => true]);
        }
    }

    /**
     * Create and authenticate a user for testing.
     *
     * @param  array<string, mixed>  $attributes  Additional user attributes
     * @return User The authenticated user
     */
    protected function createAuthenticatedUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        $this->actingAs($user, 'api');

        return $user;
    }

    /**
     * Create a user without authenticating.
     *
     * @param  array<string, mixed>  $attributes  Additional user attributes
     * @return User The created user
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Assert that an API response has the expected structure.
     *
     * @param  TestResponse  $response  The test response
     * @param  array<int|string, mixed>  $structure  Expected JSON structure
     * @param  int  $status  Expected HTTP status code
     */
    protected function assertApiResponse(
        TestResponse $response,
        array $structure,
        int $status = 200
    ): void {
        $response->assertStatus($status)
            ->assertJsonStructure($structure);
    }

    /**
     * Assert a successful API response with data.
     *
     * @param  TestResponse  $response  The test response
     * @param  array<int|string, mixed>  $dataStructure  Expected data structure
     */
    protected function assertSuccessResponse(
        TestResponse $response,
        array $dataStructure = []
    ): void {
        $structure = ['data'];

        if (!empty($dataStructure)) {
            $structure = ['data' => $dataStructure];
        }

        $this->assertApiResponse($response, $structure, 200);
    }

    /**
     * Assert a paginated API response.
     *
     * @param  TestResponse  $response  The test response
     * @param  array<int|string, mixed>  $itemStructure  Structure of each item
     */
    protected function assertPaginatedResponse(
        TestResponse $response,
        array $itemStructure = []
    ): void {
        $structure = [
            'data' => !empty($itemStructure) ? ['*' => $itemStructure] : [],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
        ];

        $this->assertApiResponse($response, $structure, 200);
    }

    /**
     * Assert a validation error response.
     *
     * @param  TestResponse  $response  The test response
     * @param  array<int, string>  $fields  Fields that should have validation errors
     */
    protected function assertValidationError(
        TestResponse $response,
        array $fields = []
    ): void {
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        if (!empty($fields)) {
            $response->assertJsonValidationErrors($fields);
        }
    }

    /**
     * Assert an unauthorized response.
     *
     * @param  TestResponse  $response  The test response
     */
    protected function assertUnauthorized(TestResponse $response): void
    {
        $response->assertStatus(401);
    }

    /**
     * Assert a forbidden response.
     *
     * @param  TestResponse  $response  The test response
     */
    protected function assertForbidden(TestResponse $response): void
    {
        $response->assertStatus(403);
    }

    /**
     * Assert a not found response.
     *
     * @param  TestResponse  $response  The test response
     */
    protected function assertNotFound(TestResponse $response): void
    {
        $response->assertStatus(404);
    }

    /**
     * Assert a created response.
     *
     * @param  TestResponse  $response  The test response
     * @param  array<int|string, mixed>  $dataStructure  Expected data structure
     */
    protected function assertCreated(
        TestResponse $response,
        array $dataStructure = []
    ): void {
        $structure = !empty($dataStructure) ? ['data' => $dataStructure] : ['data'];

        $this->assertApiResponse($response, $structure, 201);
    }

    /**
     * Assert a no content response.
     *
     * @param  TestResponse  $response  The test response
     */
    protected function assertNoContent(TestResponse $response): void
    {
        $response->assertStatus(204);
    }
}
