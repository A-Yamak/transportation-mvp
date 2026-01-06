<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

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
}
