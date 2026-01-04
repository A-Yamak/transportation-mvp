<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V3;

use App\Http\Resources\Api\V2\ApiResource as V2ApiResource;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * API V3 Base Resource
 * =============================================================================
 *
 * Extends V2 resource to inherit response transformation.
 * Override methods here when V3 needs different response format.
 *
 * Version: 3.0
 * Status: Placeholder
 *
 * =============================================================================
 */
abstract class ApiResource extends V2ApiResource
{
    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'api_version' => 'v3',
        ];
    }

    // V3-specific resource methods will be added here when this version is activated
}
