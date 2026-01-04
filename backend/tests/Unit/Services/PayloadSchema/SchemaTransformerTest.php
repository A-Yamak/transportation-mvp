<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PayloadSchema;

use App\Services\PayloadSchema\SchemaTransformer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit')]
#[Group('services')]
#[Group('payload-schema')]
class SchemaTransformerTest extends TestCase
{
    private SchemaTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transformer = new SchemaTransformer();
    }

    #[Test]
    public function validates_all_required_fields_present(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
        ];

        $required = ['name', 'email', 'phone'];

        // Should not throw exception
        $this->transformer->validateRequiredFields($data, $required);

        $this->assertTrue(true); // Assert test passes
    }

    #[Test]
    public function throws_exception_for_missing_field(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $required = ['name', 'email', 'phone'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: phone');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function throws_exception_for_multiple_missing_fields(): void
    {
        $data = [
            'name' => 'John Doe',
        ];

        $required = ['name', 'email', 'phone', 'address'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: email, phone, address');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function treats_null_as_missing(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => null,
            'phone' => '123-456-7890',
        ];

        $required = ['name', 'email', 'phone'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: email');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function treats_empty_string_as_missing(): void
    {
        $data = [
            'name' => '',
            'email' => 'john@example.com',
            'phone' => '123-456-7890',
        ];

        $required = ['name', 'email', 'phone'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: name');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function allows_zero_as_valid_value(): void
    {
        $data = [
            'name' => 'Product',
            'price' => 0,
            'quantity' => 5,
        ];

        $required = ['name', 'price', 'quantity'];

        // Should not throw exception (0 is valid)
        $this->transformer->validateRequiredFields($data, $required);

        $this->assertTrue(true);
    }

    #[Test]
    public function allows_false_as_valid_value(): void
    {
        $data = [
            'name' => 'Feature',
            'enabled' => false,
            'priority' => 1,
        ];

        $required = ['name', 'enabled', 'priority'];

        // Should not throw exception (false is valid)
        $this->transformer->validateRequiredFields($data, $required);

        $this->assertTrue(true);
    }

    #[Test]
    public function handles_empty_required_fields_array(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $required = [];

        // Should not throw exception
        $this->transformer->validateRequiredFields($data, $required);

        $this->assertTrue(true);
    }

    #[Test]
    public function validates_nested_fields_not_present(): void
    {
        $data = [
            'name' => 'John Doe',
            'contact' => [
                'email' => 'john@example.com',
            ],
        ];

        // Note: validateRequiredFields doesn't support dot notation directly
        // This is testing that it looks for top-level fields only
        $required = ['name', 'contact.phone'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required fields: contact.phone');

        $this->transformer->validateRequiredFields($data, $required);
    }

    #[Test]
    public function error_message_lists_all_missing_fields_in_order(): void
    {
        $data = [
            'field1' => 'value1',
        ];

        $required = ['field1', 'field2', 'field3', 'field4'];

        try {
            $this->transformer->validateRequiredFields($data, $required);
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('field2', $e->getMessage());
            $this->assertStringContainsString('field3', $e->getMessage());
            $this->assertStringContainsString('field4', $e->getMessage());
            $this->assertStringNotContainsString('field1', $e->getMessage());
        }
    }
}
