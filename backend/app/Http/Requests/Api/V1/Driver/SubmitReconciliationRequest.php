<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * Submit Reconciliation Request
 *
 * Validates reconciliation submission request from driver.
 * Submits daily reconciliation to Melo ERP for acknowledgment.
 */
class SubmitReconciliationRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reconciliation_id' => [
                'required',
                'string',
                'uuid',
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
            'reconciliation_id.required' => 'Reconciliation ID is required.',
            'reconciliation_id.uuid' => 'Reconciliation ID must be a valid UUID.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get reconciliation ID to submit.
     */
    public function getReconciliationId(): string
    {
        return $this->validated('reconciliation_id');
    }

    /**
     * Get optional submission notes.
     */
    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }
}
