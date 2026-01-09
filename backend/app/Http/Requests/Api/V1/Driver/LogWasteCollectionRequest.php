<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Log Waste Collection Request
 *
 * Validates waste collection data from driver app.
 * Used when driver logs waste items at a shop.
 */
class LogWasteCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Already authenticated via OAuth2
    }

    public function rules(): array
    {
        return [
            'waste_items' => ['required', 'array', 'min:1'],
            'waste_items.*.waste_item_id' => ['required', 'uuid'],
            'waste_items.*.pieces_waste' => ['required', 'integer', 'min:0'],
            'waste_items.*.notes' => ['nullable', 'string', 'max:500'],
            'driver_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'waste_items.required' => 'At least one waste item is required',
            'waste_items.array' => 'Waste items must be an array',
            'waste_items.min' => 'At least one waste item must be provided',
            'waste_items.*.waste_item_id.required' => 'Waste item ID is required',
            'waste_items.*.waste_item_id.uuid' => 'Waste item ID must be a valid UUID',
            'waste_items.*.pieces_waste.required' => 'Pieces waste count is required',
            'waste_items.*.pieces_waste.integer' => 'Pieces waste must be a number',
            'waste_items.*.pieces_waste.min' => 'Pieces waste cannot be negative',
        ];
    }

    /**
     * Get waste items data.
     */
    public function getWasteItems(): array
    {
        return $this->input('waste_items', []);
    }

    /**
     * Get driver notes.
     */
    public function getDriverNotes(): ?string
    {
        return $this->input('driver_notes');
    }
}
