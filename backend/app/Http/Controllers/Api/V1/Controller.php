<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * =============================================================================
 * API V1 Base Controller
 * =============================================================================
 *
 * All V1 API controllers should extend this class.
 *
 * Version: 1.0
 * Status: Stable
 *
 * Guidelines:
 * -----------
 * - NEVER use env() directly - always use config() helper
 * - Return consistent JSON responses using response helpers
 * - Use Form Requests for validation
 * - Use API Resources for response transformation
 *
 * Response Format:
 * ----------------
 * Success: { "data": {...}, "message": "..." }
 * Error:   { "message": "...", "errors": {...} }
 *
 * =============================================================================
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * API Version
     */
    protected const VERSION = 'v1';

    /**
     * Return a successful JSON response.
     *
     * @param  mixed  $data
     * @param  string|null  $message
     * @param  int  $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success(mixed $data = null, ?string $message = null, int $statusCode = 200)
    {
        $response = [];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a created response (201).
     *
     * @param  mixed  $data
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function created(mixed $data = null, string $message = 'Resource created successfully')
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Return a no content response (204).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function noContent()
    {
        return response()->json(null, 204);
    }

    /**
     * Return an error response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @param  array|null  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message, int $statusCode = 400, ?array $errors = null)
    {
        $response = ['message' => $message];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a not found response (404).
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFound(string $message = 'Resource not found')
    {
        return $this->error($message, 404);
    }

    /**
     * Return an unauthorized response (401).
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorized(string $message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }

    /**
     * Return a forbidden response (403).
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbidden(string $message = 'Forbidden')
    {
        return $this->error($message, 403);
    }

    /**
     * Return a paginated response.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     * @param  string|null  $resourceClass  Optional API Resource class
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginated($paginator, ?string $resourceClass = null)
    {
        $data = $resourceClass
            ? $resourceClass::collection($paginator)
            : $paginator->items();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
