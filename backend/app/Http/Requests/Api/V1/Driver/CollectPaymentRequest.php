<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\PaymentMethod;
use App\Enums\ShortageReason;
use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Collect Payment Request
 *
 * Validates payment collection data from driver at destination.
 * Supports cash, CliQ-now, and CliQ-later payment methods.
 * Requires shortage reason if amount collected is less than expected.
 */
class CollectPaymentRequest extends ApiRequest
{
    public function rules(): array
    {
        $rules = [
            'amount_collected' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::enum(PaymentMethod::class),
            ],
            'cliq_reference' => [
                'nullable',
                'string',
                'max:100',
            ],
            'shortage_reason' => [
                'nullable',
                'string',
                Rule::enum(ShortageReason::class),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];

        // Require cliq_reference for CliQ payment methods
        $paymentMethod = $this->input('payment_method');
        if (in_array($paymentMethod, [PaymentMethod::CliqNow->value, PaymentMethod::CliqLater->value])) {
            $rules['cliq_reference'] = ['required', 'string', 'max:100'];
        }

        // Check destination for amount-based validation
        $destination = $this->route('destination');
        if ($destination) {
            $amountCollected = (float) $this->input('amount_collected');
            $amountExpected = (float) $destination->amount_to_collect;

            // Require shortage_reason when amount collected is less than expected
            if ($amountCollected < $amountExpected) {
                $rules['shortage_reason'] = ['required', 'string', Rule::enum(ShortageReason::class)];
            }

            // Cannot collect more than expected
            $rules['amount_collected'][] = 'max:' . $amountExpected;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'amount_collected.required' => 'Amount collected is required.',
            'amount_collected.numeric' => 'Amount collected must be a valid number.',
            'amount_collected.min' => 'Amount collected cannot be negative.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.enum' => 'Invalid payment method.',
            'cliq_reference.max' => 'CliQ reference cannot exceed 100 characters.',
            'shortage_reason.enum' => 'Invalid shortage reason.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    public function getAmountCollected(): float
    {
        return (float) $this->validated('amount_collected');
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('payment_method'));
    }

    public function getCliQReference(): ?string
    {
        return $this->validated('cliq_reference');
    }

    public function getShortageReason(): ?ShortageReason
    {
        $reason = $this->validated('shortage_reason');
        return $reason ? ShortageReason::from($reason) : null;
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    public function isCliQPayment(): bool
    {
        $method = $this->getPaymentMethod();
        return in_array($method, [PaymentMethod::CliqNow, PaymentMethod::CliqLater]);
    }
}
