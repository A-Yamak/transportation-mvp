<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * End Day Request
 *
 * Validates end-of-day request from driver.
 * Generates daily reconciliation summary with all collections and metrics.
 */
class EndDayRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'reconciliation_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
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
            'reconciliation_date.date' => 'Reconciliation date must be a valid date.',
            'reconciliation_date.date_format' => 'Reconciliation date must be in Y-m-d format.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get reconciliation date (defaults to today).
     */
    public function getReconciliationDate(): \Carbon\Carbon
    {
        $date = $this->validated('reconciliation_date');
        return $date ? \Carbon\Carbon::parse($date) : now();
    }

    /**
     * Get optional notes.
     */
    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }
}
