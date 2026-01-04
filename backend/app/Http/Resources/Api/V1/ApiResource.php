<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =============================================================================
 * API V1 Base Resource
 * =============================================================================
 *
 * All V1 API Resources should extend this class.
 * Provides consistent response transformation for API responses.
 *
 * Version: 1.0
 *
 * Usage:
 * ------
 * Create a resource: php artisan make:resource Api/V1/UserResource
 * Then change: extends JsonResource -> extends ApiResource
 *
 * =============================================================================
 */
abstract class ApiResource extends JsonResource
{
    /**
     * Indicates if the resource's collection keys should be preserved.
     */
    public bool $preserveKeys = false;

    /**
     * The "data" wrapper that should be applied.
     */
    public static $wrap = 'data';

    /**
     * Create a new resource instance.
     */
    public function __construct(mixed $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     * Override this in child classes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'api_version' => 'v1',
        ];
    }
}
