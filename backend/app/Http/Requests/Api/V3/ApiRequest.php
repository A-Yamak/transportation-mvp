<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V3;

use App\Http\Requests\Api\V2\ApiRequest as V2ApiRequest;

/**
 * =============================================================================
 * API V3 Base Form Request
 * =============================================================================
 *
 * Extends V2 request to inherit validation behavior.
 * Override methods here when V3 needs different validation responses.
 *
 * Version: 3.0
 * Status: Placeholder
 *
 * =============================================================================
 */
abstract class ApiRequest extends V2ApiRequest
{
    // V3-specific request methods will be added here when this version is activated
}
