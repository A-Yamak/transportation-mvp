<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\DeliveryRequest;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Models\Business;
use App\Services\PayloadSchema\SchemaTransformer;

/**
 * Validates incoming delivery request creation from ERP systems.
 *
 * Required data:
 * - destinations: Array of delivery stops with coordinates
 *
 * Optional data:
 * - callback_url: Override business default callback URL
 * - scheduled_date: Future date for scheduled deliveries
 * - notes: Additional notes for the delivery
 */
class StoreDeliveryRequestRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled by middleware (active business check).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * Transforms incoming data using the business's payload schema,
     * allowing different ERPs to send data with their own field names.
     */
    protected function prepareForValidation(): void
    {
        $business = $this->get('business');

        if (! $business || ! $business->payloadSchema) {
            return;
        }

        $schema = $business->payloadSchema;
        $transformer = app(SchemaTransformer::class);

        // Transform destinations using the schema
        $destinations = $this->input('destinations', []);
        $transformedDestinations = [];

        foreach ($destinations as $dest) {
            $transformedDestinations[] = $transformer->transformIncoming($dest, $schema);
        }

        $this->merge([
            'destinations' => $transformedDestinations,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Destinations array (required, 1-25 items per Google Maps limit)
            'destinations' => ['required', 'array', 'min:1', 'max:25'],

            // External ID from client ERP (e.g., "order-123")
            'destinations.*.external_id' => ['required', 'string', 'max:100'],

            // Delivery address
            'destinations.*.address' => ['required', 'string', 'max:500'],

            // Coordinates
            'destinations.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'destinations.*.lng' => ['required', 'numeric', 'between:-180,180'],

            // Contact information (optional)
            'destinations.*.contact_name' => ['nullable', 'string', 'max:100'],
            'destinations.*.contact_phone' => ['nullable', 'string', 'max:20'],

            // Notes for this destination (optional)
            'destinations.*.notes' => ['nullable', 'string', 'max:500'],

            // Override callback URL for this request (optional)
            'callback_url' => ['nullable', 'url', 'max:500'],

            // Schedule delivery for future date (optional)
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],

            // General notes for the delivery request (optional)
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'destinations.required' => 'At least one destination is required.',
            'destinations.min' => 'At least one destination is required.',
            'destinations.max' => 'Maximum 25 destinations allowed per request (Google Maps limit).',
            'destinations.*.external_id.required' => 'Each destination must have an external_id from your ERP.',
            'destinations.*.address.required' => 'Each destination must have an address.',
            'destinations.*.lat.required' => 'Latitude is required for each destination.',
            'destinations.*.lat.between' => 'Latitude must be between -90 and 90.',
            'destinations.*.lng.required' => 'Longitude is required for each destination.',
            'destinations.*.lng.between' => 'Longitude must be between -180 and 180.',
            'callback_url.url' => 'Callback URL must be a valid URL.',
            'scheduled_date.after_or_equal' => 'Scheduled date must be today or in the future.',
        ];
    }

    /**
     * Get the authenticated business from middleware.
     */
    public function getBusiness(): Business
    {
        return $this->get('business');
    }

    /**
     * Get validated destinations array.
     *
     * @return array<int, array{external_id: string, address: string, lat: float, lng: float, contact_name?: string, contact_phone?: string, notes?: string}>
     */
    public function getDestinations(): array
    {
        return $this->validated('destinations');
    }

    /**
     * Get callback URL (request-specific or null).
     */
    public function getCallbackUrl(): ?string
    {
        return $this->validated('callback_url');
    }

    /**
     * Get scheduled date if provided.
     */
    public function getScheduledDate(): ?string
    {
        return $this->validated('scheduled_date');
    }

    /**
     * Get notes for the delivery request.
     */
    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }
}
