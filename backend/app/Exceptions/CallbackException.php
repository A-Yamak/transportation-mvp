<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown when ERP callback fails.
 *
 * This includes:
 * - HTTP request failures
 * - Timeout errors
 * - Authentication failures
 * - Invalid callback configurations
 */
class CallbackException extends RuntimeException
{
    /**
     * Create exception for failed callback send.
     */
    public static function sendFailed(string $url, int $statusCode, ?string $reason = null): self
    {
        $message = "Callback to {$url} failed with HTTP status {$statusCode}";

        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self($message);
    }

    /**
     * Create exception for callback timeout.
     */
    public static function timeout(string $url, int $timeoutSeconds): self
    {
        return new self("Callback to {$url} timed out after {$timeoutSeconds} seconds");
    }

    /**
     * Create exception for missing callback URL.
     */
    public static function missingUrl(string $businessId): self
    {
        return new self("No callback URL configured for business {$businessId}");
    }

    /**
     * Create exception for missing payload schema.
     */
    public static function missingSchema(string $businessId): self
    {
        return new self("No payload schema configured for business {$businessId}");
    }

    /**
     * Create exception for network errors.
     */
    public static function networkError(string $url, string $error): self
    {
        return new self("Network error while calling {$url}: {$error}");
    }
}
