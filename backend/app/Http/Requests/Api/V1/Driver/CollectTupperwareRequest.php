<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * Collect Tupperware Request
 *
 * Validates tupperware pickup data from driver at destination.
 * Tracks pickup of reusable containers (boxes, trays, bags) from shops.
 */
class CollectTupperwareRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'tupperware' => [
                'required',
                'array',
                'min:1',
            ],
            'tupperware.*.product_type' => [
                'required',
                'string',
                'max:50',
            ],
            'tupperware.*.quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tupperware.required' => 'At least one tupperware item is required.',
            'tupperware.array' => 'Tupperware must be an array.',
            'tupperware.min' => 'At least one tupperware item is required.',
            'tupperware.*.product_type.required' => 'Product type is required for each item.',
            'tupperware.*.product_type.max' => 'Product type cannot exceed 50 characters.',
            'tupperware.*.quantity.required' => 'Quantity is required for each item.',
            'tupperware.*.quantity.integer' => 'Quantity must be a whole number.',
            'tupperware.*.quantity.min' => 'Quantity cannot be negative.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get tupperware items array.
     *
     * @return array<int, array{product_type: string, quantity: int}>
     */
    public function getTupperwareItems(): array
    {
        return $this->validated('tupperware') ?? [];
    }

    /**
     * Get notes if provided.
     */
    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    /**
     * Check if any tupperware was collected.
     */
    public function hasTupperware(): bool
    {
        $items = $this->getTupperwareItems();
        return count($items) > 0 && collect($items)->sum('quantity') > 0;
    }
}
