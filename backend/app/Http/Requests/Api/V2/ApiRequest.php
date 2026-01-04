<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\Api\V1\ApiRequest as V1ApiRequest;

/**
 * =============================================================================
 * API V2 Base Form Request
 * =============================================================================
 *
 * Extends V1 request to inherit validation behavior.
 * Override methods here when V2 needs different validation responses.
 *
 * Version: 2.0
 * Status: Placeholder
 *
 * =============================================================================
 */
abstract class ApiRequest extends V1ApiRequest
{
    // V2-specific request methods will be added here when this version is activated
}
