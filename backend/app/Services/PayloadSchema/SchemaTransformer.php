<?php

declare(strict_types=1);

namespace App\Services\PayloadSchema;

use App\Models\BusinessPayloadSchema;
use App\Models\Destination;
use InvalidArgumentException;

/**
 * Transforms API payloads between internal format and business-specific schemas.
 *
 * Allows different ERPs to integrate with different field names:
 * - ERP A sends "order_id", we map to "external_id"
 * - ERP B sends "delivery_location", we map to "address"
 *
 * Strategy: Delegates to BusinessPayloadSchema model methods for actual mapping,
 * adds batch processing and validation logic.
 */
class SchemaTransformer
{
    /**
     * Transform incoming request data to internal format.
     *
     * @param  array  $data  Incoming data from ERP
     * @param  BusinessPayloadSchema  $schema  Business schema configuration
     * @return array Transformed data in internal format
     */
    public function transformIncoming(array $data, BusinessPayloadSchema $schema): array
    {
        // Delegate to model's method for field mapping
        return [
            'external_id' => $schema->getFromRequest($data, 'external_id'),
            'address' => $schema->getFromRequest($data, 'address'),
            'lat' => $schema->getFromRequest($data, 'lat'),
            'lng' => $schema->getFromRequest($data, 'lng'),
            'notes' => $schema->getFromRequest($data, 'notes'),
            'recipient_name' => $schema->getFromRequest($data, 'recipient_name'),
        ];
    }

    /**
     * Transform multiple destinations from incoming request.
     *
     * @param  array  $destinations  Array of destination data from ERP
     * @param  BusinessPayloadSchema  $schema
     * @return array Array of transformed destinations
     */
    public function transformIncomingDestinations(
        array $destinations,
        BusinessPayloadSchema $schema
    ): array {
        return array_map(
            fn ($dest) => $this->transformIncoming($dest, $schema),
            $destinations
        );
    }

    /**
     * Transform outgoing data for callback to ERP.
     *
     * Delegates to model's transformForCallback method.
     *
     * @param  Destination  $destination  Completed destination
     * @param  BusinessPayloadSchema  $schema  Business schema configuration
     * @return array Transformed data in ERP format
     */
    public function transformCallback(
        Destination $destination,
        BusinessPayloadSchema $schema
    ): array {
        // Delegate to model's method
        return $schema->transformForCallback($destination);
    }

    /**
     * Validate that required fields are present in incoming data.
     *
     * @param  array  $data  Incoming data
     * @param  array  $requiredFields  Array of required field names
     *
     * @throws InvalidArgumentException if required fields are missing
     */
    public function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new InvalidArgumentException(
                'Missing required fields: '.implode(', ', $missing)
            );
        }
    }
}
