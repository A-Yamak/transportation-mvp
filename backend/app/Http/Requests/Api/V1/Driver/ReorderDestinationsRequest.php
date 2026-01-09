<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * Reorder Destinations Request
 *
 * Validates destination reordering (drag-drop) request from driver.
 * Must contain all destination IDs in the desired order.
 */
class ReorderDestinationsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'destination_ids' => [
                'required',
                'array',
                'min:2', // Must have at least 2 destinations to reorder
            ],
            'destination_ids.*' => [
                'required',
                'string',
                'uuid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_ids.required' => 'Destination IDs are required.',
            'destination_ids.array' => 'Destination IDs must be an array.',
            'destination_ids.min' => 'At least 2 destinations are required to reorder.',
            'destination_ids.*.required' => 'Each destination ID is required.',
            'destination_ids.*.uuid' => 'Each destination ID must be a valid UUID.',
        ];
    }

    /**
     * Get ordered destination IDs.
     *
     * @return array<int, string>
     */
    public function getDestinationIds(): array
    {
        return $this->validated('destination_ids') ?? [];
    }

    /**
     * Get destination IDs with sequence order.
     *
     * @return array<int, array{id: string, sequence_order: int}>
     */
    public function getDestinationsWithSequence(): array
    {
        return collect($this->getDestinationIds())
            ->map(function ($id, $index) {
                return [
                    'id' => $id,
                    'sequence_order' => $index + 1,
                ];
            })
            ->values()
            ->toArray();
    }
}
