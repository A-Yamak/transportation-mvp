<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessPayloadSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessPayloadSchema>
 */
class BusinessPayloadSchemaFactory extends Factory
{
    protected $model = BusinessPayloadSchema::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'request_schema' => BusinessPayloadSchema::defaultRequestSchema(),
            'callback_schema' => BusinessPayloadSchema::defaultCallbackSchema(),
        ];
    }

    /**
     * Custom ERP format (different field names).
     */
    public function customErp(): static
    {
        return $this->state(fn () => [
            'request_schema' => [
                'external_id' => 'order_id',
                'address' => 'delivery_address',
                'lat' => 'coordinates.latitude',
                'lng' => 'coordinates.longitude',
                'notes' => 'special_instructions',
            ],
            'callback_schema' => [
                'external_id' => 'order_id',
                'status' => 'delivery_status',
                'completed_at' => 'delivered_timestamp',
                'recipient_name' => 'received_by',
            ],
        ]);
    }

    /**
     * Sweets Factory ERP format.
     */
    public function sweetsFactory(): static
    {
        return $this->state(fn () => [
            'request_schema' => [
                'external_id' => 'sf_order_id',
                'address' => 'customer_address',
                'lat' => 'geo.lat',
                'lng' => 'geo.lng',
                'notes' => 'delivery_notes',
            ],
            'callback_schema' => [
                'external_id' => 'sf_order_id',
                'status' => 'order_status',
                'completed_at' => 'delivery_time',
                'recipient_name' => 'recipient',
            ],
        ]);
    }
}
