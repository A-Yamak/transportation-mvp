<?php

declare(strict_types=1);

namespace App\Services\Callback;

use App\Models\Destination;
use App\Services\PayloadSchema\SchemaTransformer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends delivery completion callbacks to client ERP systems.
 *
 * When a driver marks a destination as completed (or failed), this service
 * sends a HTTP callback to the client's configured webhook URL.
 */
class CallbackService
{
    protected SchemaTransformer $schemaTransformer;

    public function __construct(SchemaTransformer $schemaTransformer)
    {
        $this->schemaTransformer = $schemaTransformer;
    }

    /**
     * Send completion callback for a destination.
     *
     * @param  Destination  $destination  Completed/failed destination
     * @return bool True if callback was sent successfully
     */
    public function sendCompletionCallback(Destination $destination): bool
    {
        $business = $destination->deliveryRequest->business;

        if (! $business->callback_url) {
            Log::warning('No callback URL configured for business', [
                'business_id' => $business->id,
                'destination_id' => $destination->id,
            ]);

            return false;
        }

        $schema = $business->payloadSchema;

        if (! $schema) {
            Log::warning('No payload schema configured for business', [
                'business_id' => $business->id,
                'destination_id' => $destination->id,
            ]);

            return false;
        }

        $payload = $this->schemaTransformer->transformCallback($destination, $schema);

        return $this->sendCallback($business->callback_url, $payload, $business->callback_api_key);
    }

    /**
     * Send HTTP callback to ERP.
     *
     * @param  string  $url  Callback URL
     * @param  array  $payload  Data to send
     * @param  string|null  $apiKey  Optional API key for authentication
     * @return bool True if successful
     */
    protected function sendCallback(string $url, array $payload, ?string $apiKey = null): bool
    {
        try {
            $request = Http::timeout(30);

            if ($apiKey) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url, $payload);

            $success = $response->successful();

            Log::info('Callback sent to ERP', [
                'url' => $url,
                'status' => $response->status(),
                'success' => $success,
                'payload' => $payload,
            ]);

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send callback to ERP', [
                'url' => $url,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send test callback to verify ERP integration.
     *
     * @param  string  $url  Callback URL
     * @param  string|null  $apiKey  Optional API key
     * @return array ['success' => bool, 'status' => int, 'message' => string]
     */
    public function sendTestCallback(string $url, ?string $apiKey = null): array
    {
        $testPayload = [
            'external_id' => 'TEST-123',
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            'message' => 'This is a test callback from Transportation MVP',
        ];

        try {
            $request = Http::timeout(10);

            if ($apiKey) {
                $request = $request->withToken($apiKey);
            }

            $response = $request->post($url, $testPayload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Test callback sent successfully'
                    : 'Callback failed with status '.$response->status(),
                'response_body' => $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 0,
                'message' => 'Exception: '.$e->getMessage(),
            ];
        }
    }
}
