<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Api\V2\Controller as V2Controller;

/**
 * =============================================================================
 * API V3 Base Controller
 * =============================================================================
 *
 * Extends V2 controller to inherit all response helpers.
 * Override methods here when V3 needs different behavior.
 *
 * Version: 3.0
 * Status: Placeholder (Ready for future use)
 *
 * Guidelines:
 * -----------
 * - V3 may introduce breaking changes from V2
 * - Use new API Resources for response transformation
 * - Consider deprecation notices for V1/V2 when V3 is stable
 *
 * =============================================================================
 */
abstract class Controller extends V2Controller
{
    /**
     * API Version
     */
    protected const VERSION = 'v3';

    // V3-specific methods will be added here when this version is activated
}
