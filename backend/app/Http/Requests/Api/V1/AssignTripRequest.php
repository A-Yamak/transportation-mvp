<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

/**
 * Assign Trip Request
 *
 * Validates data for assigning a delivery request to a driver.
 */
class AssignTripRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_request_id' => ['required', 'uuid', 'exists:delivery_requests,id'],
            'driver_id' => ['required', 'uuid', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],
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
            'delivery_request_id.required' => 'Please specify the delivery request to assign.',
            'delivery_request_id.exists' => 'The specified delivery request does not exist.',
            'driver_id.required' => 'Please specify a driver.',
            'driver_id.exists' => 'The specified driver does not exist.',
            'vehicle_id.required' => 'Please specify a vehicle.',
            'vehicle_id.exists' => 'The specified vehicle does not exist.',
            'scheduled_date.after_or_equal' => 'Scheduled date must be today or later.',
        ];
    }

    public function getDeliveryRequestId(): string
    {
        return $this->validated('delivery_request_id');
    }

    public function getDriverId(): string
    {
        return $this->validated('driver_id');
    }

    public function getVehicleId(): string
    {
        return $this->validated('vehicle_id');
    }

    public function getScheduledDate(): ?\DateTime
    {
        $date = $this->validated('scheduled_date');
        return $date ? new \DateTime($date) : null;
    }
}
