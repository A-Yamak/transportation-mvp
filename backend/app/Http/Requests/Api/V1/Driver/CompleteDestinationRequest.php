<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\ItemDeliveryReason;
use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Complete Destination Request
 *
 * Validates data when driver completes a delivery at a destination.
 * Supports optional signature and photo (base64 encoded).
 */
class CompleteDestinationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'signature' => ['nullable', 'string'], // Base64 encoded
            'photo' => ['nullable', 'string'], // Base64 encoded

            // Item-level delivery data (optional for backward compatibility)
            'items' => ['nullable', 'array'],
            'items.*.order_item_id' => ['required_with:items', 'string', 'max:100'],
            'items.*.quantity_ordered' => ['nullable', 'integer', 'min:0'],
            'items.*.quantity_delivered' => ['required_with:items', 'integer', 'min:0'],
            'items.*.reason' => ['nullable', 'string', Rule::enum(ItemDeliveryReason::class)],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_name.max' => 'Recipient name cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    public function getRecipientName(): ?string
    {
        return $this->validated('recipient_name');
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    public function getSignature(): ?string
    {
        return $this->validated('signature');
    }

    public function getPhoto(): ?string
    {
        return $this->validated('photo');
    }

    /**
     * Check if request includes item-level data.
     */
    public function hasItemData(): bool
    {
        return ! empty($this->validated('items'));
    }

    /**
     * Get items array.
     *
     * @return array<int, array{order_item_id: string, quantity_ordered?: int, quantity_delivered: int, reason?: string, notes?: string}>
     */
    public function getItems(): array
    {
        return $this->validated('items') ?? [];
    }
}
