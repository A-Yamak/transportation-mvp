<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =============================================================================
 * Auth Token Resource
 * =============================================================================
 * Transforms authentication token response data.
 *
 * Usage:
 *   return new AuthTokenResource([
 *       'user' => $user,
 *       'tokens' => $tokens,
 *       'message' => 'Login successful',
 *   ]);
 * =============================================================================
 */
class AuthTokenResource extends JsonResource
{
    /**
     * Create a new resource instance.
     *
     * @param  array{user?: \App\Models\User|null, tokens: array, message?: string}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tokens = $this->resource['tokens'];
        $user = $this->resource['user'] ?? null;
        $message = $this->resource['message'] ?? null;

        $response = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => 'Bearer',
        ];

        if ($user) {
            // Return user data directly (not wrapped) for auth responses
            $response['user'] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ];
        }

        if ($message) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * Create a response for successful registration.
     *
     * @param  \App\Models\User  $user
     * @param  array  $tokens
     * @return static
     */
    public static function forRegistration($user, array $tokens): static
    {
        return new static([
            'user' => $user,
            'tokens' => $tokens,
            'message' => 'Registration successful',
        ]);
    }

    /**
     * Create a response for successful login.
     *
     * @param  \App\Models\User  $user
     * @param  array  $tokens
     * @return static
     */
    public static function forLogin($user, array $tokens): static
    {
        return new static([
            'user' => $user,
            'tokens' => $tokens,
            'message' => 'Login successful',
        ]);
    }

    /**
     * Create a response for token refresh.
     *
     * @param  array  $tokens
     * @return static
     */
    public static function forRefresh(array $tokens): static
    {
        return new static([
            'tokens' => $tokens,
        ]);
    }
}
