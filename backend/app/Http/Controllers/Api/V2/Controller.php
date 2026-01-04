<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Api\V1\Controller as V1Controller;

/**
 * =============================================================================
 * API V2 Base Controller
 * =============================================================================
 *
 * Extends V1 controller to inherit response helpers.
 * Override methods here when V2 needs different behavior.
 *
 * Version: 2.0
 * Status: Placeholder (Ready for future use)
 *
 * Guidelines:
 * -----------
 * - V2 should be backwards compatible where possible
 * - Document breaking changes from V1
 * - Use new API Resources for response transformation
 *
 * =============================================================================
 */
abstract class Controller extends V1Controller
{
    /**
     * API Version
     */
    protected const VERSION = 'v2';

    // V2-specific methods will be added here when this version is activated
}
