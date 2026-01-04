<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V2;

use App\Http\Resources\Api\V1\ApiResource as V1ApiResource;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * API V2 Base Resource
 * =============================================================================
 *
 * Extends V1 resource to inherit response transformation.
 * Override methods here when V2 needs different response format.
 *
 * Version: 2.0
 * Status: Placeholder
 *
 * =============================================================================
 */
abstract class ApiResource extends V1ApiResource
{
    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'api_version' => 'v2',
        ];
    }

    // V2-specific resource methods will be added here when this version is activated
}
