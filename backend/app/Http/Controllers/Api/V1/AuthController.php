<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * =============================================================================
 * Authentication Controller
 * =============================================================================
 * Handles user authentication using OAuth2 Password Grant.
 *
 * ARCHITECTURE DECISIONS:
 *
 * 1. WHY JsonResponse INSTEAD OF JsonResource FOR AUTH ENDPOINTS?
 *    - JsonResource automatically wraps responses in {"data": {...}}
 *    - Auth responses need a flat structure for frontend compatibility:
 *      {"access_token": "...", "user": {...}} NOT {"data": {"access_token": ...}}
 *    - Standard CRUD endpoints SHOULD use Resources for consistent wrapping
 *    - Auth endpoints are special - they return tokens, not model resources
 *
 * 2. WHY PROXY TO PASSPORT'S /oauth/token ENDPOINT?
 *    - Keeps client_id and client_secret secure on the server
 *    - Frontend only sends email/password, never OAuth credentials
 *    - Alternative: Personal Access Tokens (simpler but less secure)
 *    - Alternative: Sanctum (simpler, but no refresh tokens)
 *
 * 3. WHY USE FORM REQUESTS INSTEAD OF INLINE VALIDATION?
 *    - Reusable validation logic (can be used in multiple controllers)
 *    - Cleaner controllers (validation moved to dedicated class)
 *    - Typed accessor methods ($request->getEmail() vs $request->input('email'))
 *    - Self-documenting API (request classes show what's expected)
 *    - Testable in isolation
 *
 * 4. WHY DB::transaction() IN REGISTER?
 *    - User creation and token generation are atomic
 *    - If token generation fails, user is NOT left in database
 *    - Prevents orphaned users that can't authenticate
 *
 * @group Authentication
 * =============================================================================
 */
class AuthController extends Controller
{
    /**
     * Register a new user and return tokens.
     *
     * NOTE: We cannot use DB::transaction here because getTokensForUser()
     * makes an HTTP request to /oauth/token which runs in a SEPARATE database
     * connection. That connection cannot see uncommitted rows from our transaction.
     *
     * If token generation fails after user creation, the user still exists
     * and can login later - this is acceptable behavior.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Create user first (must be committed before /oauth/token can see it)
        $user = User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            'password' => Hash::make($request->getPassword()),
        ]);

        // Get tokens via password grant (separate HTTP request)
        $tokens = $this->getTokensForUser($request->getEmail(), $request->getPassword());

        if (! $tokens) {
            // User is created but token generation failed
            // They can still login later - don't delete the user
            return response()->json([
                'message' => 'Registration successful but automatic login failed. Please try logging in.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 201);
        }

        return response()->json($this->formatAuthResponse($user, $tokens, 'Registration successful'), 201);
    }

    /**
     * Login with email and password, return tokens.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Verify credentials first
        if (! Auth::attempt($request->credentials())) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Get tokens via password grant
        $tokens = $this->getTokensForUser($request->getEmail(), $request->getPassword());

        if (! $tokens) {
            return response()->json([
                'message' => 'Authentication failed',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        return response()->json($this->formatAuthResponse($user, $tokens, 'Login successful'));
    }

    /**
     * Refresh access token using refresh_token.
     *
     * @param RefreshTokenRequest $request
     * @return JsonResponse
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->refreshTokens($request->getRefreshToken());

        if (! $tokens) {
            return response()->json([
                'message' => 'Invalid or expired refresh token',
            ], 401);
        }

        return response()->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout the current user (revoke tokens).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke the current access token
        $token = $user->token();
        if ($token) {
            $token->revoke();

            // Also revoke the refresh token
            $refreshToken = \DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $token->id)
                ->update(['revoked' => true]);
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get the authenticated user.
     *
     * NOTE: This endpoint DOES use UserResource (not JsonResponse) because:
     * - It returns a single model resource (standard pattern)
     * - The {"data": {...}} wrapper is expected here
     * - Consistent with other model endpoints (GET /users/{id})
     *
     * @param Request $request
     * @return UserResource
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Get the password grant client credentials.
     *
     * Tries config first (for production with env vars), falls back to
     * database lookup (for development with plain text secret from setup:passport).
     *
     * @return array{id: string, secret: string}|null
     */
    protected function getPasswordClient(): ?array
    {
        // Try config first (production - env vars set)
        $clientId = config('passport.password_client.id');
        $clientSecret = config('passport.password_client.secret');

        if ($clientId && $clientSecret) {
            return ['id' => $clientId, 'secret' => $clientSecret];
        }

        // Fallback: find password grant client from database (development)
        $client = DB::table('oauth_clients')
            ->whereJsonContains('grant_types', 'password')
            ->where('revoked', false)
            ->first();

        if ($client) {
            return ['id' => $client->id, 'secret' => $client->secret];
        }

        return null;
    }

    /**
     * Get OAuth tokens for user via Password Grant.
     *
     * WHY INTERNAL HTTP REQUEST TO /oauth/token?
     * - Passport's token endpoint handles all OAuth2 complexity
     * - We act as a proxy, hiding client credentials from frontend
     * - Alternative: $user->createToken() - but that's for Personal Access Tokens,
     *   which don't support refresh tokens
     *
     * WHY config('app.url') INSTEAD OF url()?
     * - Works in containerized environments where internal URL differs
     * - Configurable per environment (localhost vs production domain)
     *
     * @param string $email
     * @param string $password
     * @return array|null Returns {access_token, refresh_token, expires_in} or null on failure
     */
    protected function getTokensForUser(string $email, string $password): ?array
    {
        $client = $this->getPasswordClient();
        if (! $client) {
            return null;
        }

        // Internal request to Passport's OAuth token endpoint
        // This keeps client_id/client_secret secure on server side
        $response = Http::asForm()->post(config('app.url') . '/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $client['id'],
            'client_secret' => $client['secret'],
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Refresh tokens using refresh_token.
     *
     * @param string $refreshToken
     * @return array|null
     */
    protected function refreshTokens(string $refreshToken): ?array
    {
        $client = $this->getPasswordClient();
        if (! $client) {
            return null;
        }

        $response = Http::asForm()->post(config('app.url') . '/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client['id'],
            'client_secret' => $client['secret'],
            'refresh_token' => $refreshToken,
            'scope' => '',
        ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Format authentication response with user and tokens.
     *
     * WHY NOT USE UserResource HERE?
     * - UserResource wraps in {"data": {...}} which we don't want for auth
     * - We need flat user data directly in the response
     * - This method gives us full control over the response structure
     *
     * WHY toIso8601String() FOR DATES?
     * - ISO 8601 is the standard format for JSON APIs
     * - Frontend can parse it reliably across timezones
     * - Example: "2025-12-29T20:14:26+00:00"
     *
     * @param User $user
     * @param array $tokens
     * @param string|null $message
     * @return array Flat response structure (no "data" wrapper)
     */
    protected function formatAuthResponse(User $user, array $tokens, ?string $message = null): array
    {
        $response = [
            // Token data from Passport
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => 'Bearer',

            // User data (flat, not wrapped in "data")
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return $response;
    }
}
