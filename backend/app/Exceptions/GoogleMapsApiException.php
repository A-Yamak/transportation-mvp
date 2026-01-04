<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown when Google Maps API encounters an error.
 *
 * This includes:
 * - Missing or invalid API key
 * - Rate limit exceeded
 * - API request failures
 * - Invalid responses
 */
class GoogleMapsApiException extends RuntimeException
{
    /**
     * Create exception for missing or invalid API key.
     */
    public static function invalidApiKey(): self
    {
        return new self('Google Maps API key is missing or invalid. Configure GOOGLE_MAPS_API_KEY in .env');
    }

    /**
     * Create exception for rate limit exceeded.
     */
    public static function rateLimitExceeded(): self
    {
        return new self('Google Maps API rate limit exceeded. Please try again later.');
    }

    /**
     * Create exception for failed API request.
     */
    public static function requestFailed(string $message, int $statusCode = 0): self
    {
        $fullMessage = "Google Maps API request failed: {$message}";

        if ($statusCode > 0) {
            $fullMessage .= " (HTTP {$statusCode})";
        }

        return new self($fullMessage);
    }

    /**
     * Create exception for invalid response from API.
     */
    public static function invalidResponse(string $reason): self
    {
        return new self("Invalid response from Google Maps API: {$reason}");
    }

    /**
     * Create exception for too many waypoints.
     */
    public static function tooManyWaypoints(int $count, int $max): self
    {
        return new self("Cannot optimize {$count} waypoints. Maximum {$max} allowed by Google Maps API.");
    }

    /**
     * Create exception for invalid coordinates.
     */
    public static function invalidCoordinates(string $reason): self
    {
        return new self("Invalid coordinates: {$reason}");
    }
}
