<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests using the X-API-Key header.
 *
 * This middleware is used for B2B/ERP integrations where businesses
 * authenticate with their API key instead of OAuth tokens.
 *
 * The authenticated Business is bound to the request and can be accessed
 * via $request->business in controllers.
 */
class AuthenticateBusinessApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return response()->json([
                'message' => 'API key required. Please provide X-API-Key header.',
            ], 401);
        }

        $business = Business::where('api_key', $apiKey)->first();

        if (! $business) {
            return response()->json([
                'message' => 'Invalid API key.',
            ], 401);
        }

        if (! $business->is_active) {
            return response()->json([
                'message' => 'Business account is inactive. Please contact support.',
            ], 403);
        }

        // Bind the business to the request for use in controllers
        $request->merge(['business' => $business]);
        $request->setUserResolver(fn () => $business);

        return $next($request);
    }
}
