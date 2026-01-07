<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PayloadSchema;

use App\Models\Business;
use App\Models\BusinessPayloadSchema;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Services\PayloadSchema\SchemaTransformer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('services')]
#[Group('payload-schema')]
#[Group('transformer')]
class SchemaTransformerTest extends TestCase
{
    private SchemaTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transformer = new SchemaTransformer();
    }

    #[Test]
    public function transforms_incoming_data_with_default_schema(): void
    {
        $schema = BusinessPayloadSchema::factory()->create([
            'request_schema' => BusinessPayloadSchema::defaultRequestSchema(),
        ]);

        $incomingData = [
            'external_id' => 'ORDER-12345',
            'address' => '123 Main St, Amman',
            'lat' => 31.9539,
            'lng' => 35.9106,
            'notes' => 'Ring doorbell twice',
        ];

        $result = $this->transformer->transformIncoming($incomingData, $schema);

        $this->assertEquals('ORDER-12345', $result['external_id']);
        $this->assertEquals('123 Main St, Amman', $result['address']);
        $this->assertEquals(31.9539, $result['lat']);
        $this->assertEquals(35.9106, $result['lng']);
        $this->assertEquals('Ring doorbell twice', $result['notes']);
    }

    #[Test]
    public function transforms_incoming_data_with_custom_schema(): void
    {
        $schema = BusinessPayloadSchema::factory()->create([
            'request_schema' => [
                'external_id' => 'order_number',
                'address' => 'delivery_location',
                'lat' => 'coordinates.latitude',
                'lng' => 'coordinates.longitude',
                'notes' => 'special_instructions',
            ],
        ]);

        $incomingData = [
            'order_number' => 'ORD-999',
            'delivery_location' => '456 King St, Zarqa',
            'coordinates' => [
                'latitude' => 32.0667,
                'longitude' => 36.1000,
            ],
            'special_instructions' => 'Call on arrival',
        ];

        $result = $this->transformer->transformIncoming($incomingData, $schema);

        $this->assertEquals('ORD-999', $result['external_id']);
        $this->assertEquals('456 King St, Zarqa', $result['address']);
        $this->assertEquals(32.0667, $result['lat']);
        $this->assertEquals(36.1000, $result['lng']);
        $this->assertEquals('Call on arrival', $result['notes']);
    }

    #[Test]
    public function transforms_multiple_destinations(): void
    {
        $schema = BusinessPayloadSchema::factory()->create([
            'request_schema' => BusinessPayloadSchema::defaultRequestSchema(),
        ]);

        $destinations = [
            [
                'external_id' => 'ORDER-1',
                'address' => '123 Main St',
                'lat' => 31.95,
                'lng' => 35.91,
            ],
            [
                'external_id' => 'ORDER-2',
                'address' => '456 King St',
                'lat' => 31.96,
                'lng' => 35.92,
            ],
        ];

        $result = $this->transformer->transformIncomingDestinations($destinations, $schema);

        $this->assertCount(2, $result);
        $this->assertEquals('ORDER-1', $result[0]['external_id']);
        $this->assertEquals('ORDER-2', $result[1]['external_id']);
    }

    #[Test]
    public function transforms_callback_data_with_default_schema(): void
    {
        $business = Business::factory()->create();
        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
            'callback_schema' => BusinessPayloadSchema::defaultCallbackSchema(),
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'external_id' => 'ORDER-12345',
            'recipient_name' => 'John Doe',
        ]);

        $result = $this->transformer->transformCallback($destination, $schema);

        $this->assertEquals('ORDER-12345', $result['external_id']);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('John Doe', $result['recipient_name']);
        $this->assertArrayHasKey('completed_at', $result);
    }

    #[Test]
    public function transforms_callback_data_with_custom_schema(): void
    {
        $business = Business::factory()->create();
        $schema = BusinessPayloadSchema::factory()->create([
            'business_id' => $business->id,
            'callback_schema' => [
                // Format: internal_field => output_field
                'external_id' => 'order_id',
                'status' => 'delivery_status',
                'completed_at' => 'delivered_at',
                'recipient_name' => 'received_by',
            ],
        ]);

        $deliveryRequest = DeliveryRequest::factory()->create([
            'business_id' => $business->id,
        ]);

        $destination = Destination::factory()->completed()->create([
            'delivery_request_id' => $deliveryRequest->id,
            'external_id' => 'ORD-999',
            'recipient_name' => 'Jane Smith',
        ]);

        $result = $this->transformer->transformCallback($destination, $schema);

        $this->assertEquals('ORD-999', $result['order_id']);
        $this->assertEquals('completed', $result['delivery_status']);
        $this->assertEquals('Jane Smith', $result['received_by']);
        $this->assertArrayHasKey('delivered_at', $result);
    }

    #[Test]
    public function validates_required_fields_exist(): void
    {
        $data = [
            'address' => '123 Main St',
            'lat' => 31.9539,
            // Missing 'lng'
        ];

        $required = ['address', 'lat', 'lng'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: lng');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function validates_multiple_missing_fields(): void
    {
        $data = [
            'address' => '123 Main St',
        ];

        $required = ['address', 'lat', 'lng', 'external_id'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: lat, lng, external_id');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function validates_null_values_as_missing(): void
    {
        $data = [
            'address' => '123 Main St',
            'lat' => null,
            'lng' => 35.9106,
        ];

        $required = ['address', 'lat', 'lng'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: lat');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function validates_empty_strings_as_missing(): void
    {
        $data = [
            'address' => '',
            'lat' => 31.9539,
            'lng' => 35.9106,
        ];

        $required = ['address', 'lat', 'lng'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: address');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function passes_validation_when_all_fields_present(): void
    {
        $data = [
            'address' => '123 Main St',
            'lat' => 31.9539,
            'lng' => 35.9106,
            'external_id' => 'ORDER-123',
        ];

        $required = ['address', 'lat', 'lng', 'external_id'];

        // Should not throw exception
        $this->transformer->validateRequiredFields($data, $required);

        $this->assertTrue(true); // Assert test passes
    }

    #[Test]
    public function handles_nested_field_mapping_via_schema(): void
    {
        $schema = BusinessPayloadSchema::factory()->create([
            'request_schema' => [
                'external_id' => 'order.id',
                'address' => 'delivery.address',
                'lat' => 'delivery.location.lat',
                'lng' => 'delivery.location.lng',
                'notes' => 'notes',
            ],
        ]);

        $incomingData = [
            'order' => [
                'id' => 'ORD-456',
            ],
            'delivery' => [
                'address' => '789 Queen St',
                'location' => [
                    'lat' => 32.5500,
                    'lng' => 35.8500,
                ],
            ],
            'notes' => 'Handle with care',
        ];

        $result = $this->transformer->transformIncoming($incomingData, $schema);

        $this->assertEquals('ORD-456', $result['external_id']);
        $this->assertEquals('789 Queen St', $result['address']);
        $this->assertEquals(32.5500, $result['lat']);
        $this->assertEquals(35.8500, $result['lng']);
        $this->assertEquals('Handle with care', $result['notes']);
    }
}
