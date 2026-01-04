<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =============================================================================
 * User Resource
 * =============================================================================
 * Transforms User model for API responses.
 *
 * WHEN TO USE JsonResource:
 * - Standard CRUD endpoints (GET /users, GET /users/{id})
 * - When you want consistent {"data": {...}} wrapping
 * - When returning model collections (automatic pagination)
 *
 * WHEN NOT TO USE JsonResource:
 * - Auth responses (login, register) - need flat structure
 * - Custom responses that don't follow resource pattern
 * - When frontend expects unwrapped data
 *
 * RESPONSE FORMAT:
 * Single resource:  {"data": {"id": 1, "name": "..."}}
 * Collection:       {"data": [{"id": 1}, {"id": 2}], "links": {...}, "meta": {...}}
 *
 * @mixin \App\Models\User
 * =============================================================================
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
