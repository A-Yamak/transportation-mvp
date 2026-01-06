<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\FailureReason;
use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Fail Destination Request
 *
 * Validates data when driver cannot complete a delivery (shop closed, wrong address, etc.)
 */
class FailDestinationRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(FailureReason::class)],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'reason.required' => 'A reason for failure is required.',
            'reason.enum' => 'Invalid failure reason. Valid options: not_home, refused, wrong_address, inaccessible, other.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    public function getReason(): FailureReason
    {
        return FailureReason::from($this->validated('reason'));
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }
}
