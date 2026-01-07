<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BusinessPayloadSchema Model
 *
 * Dynamic API format mapping per business.
 * Allows different ERPs to integrate with different field names.
 *
 * Example request_schema:
 * {
 *   "external_id": "order_id",
 *   "address": "delivery_address",
 *   "lat": "coordinates.latitude",
 *   "lng": "coordinates.longitude"
 * }
 *
 * @property string $id UUID
 * @property string $business_id FK to businesses (unique)
 * @property array $request_schema Maps incoming fields
 * @property array $callback_schema Maps outgoing callback fields
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BusinessPayloadSchema extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'business_id',
        'request_schema',
        'callback_schema',
    ];

    protected function casts(): array
    {
        return [
            'request_schema' => 'array',
            'callback_schema' => 'array',
        ];
    }

    /**
     * Business this schema belongs to.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the default request schema.
     */
    public static function defaultRequestSchema(): array
    {
        return [
            'external_id' => 'external_id',
            'address' => 'address',
            'lat' => 'lat',
            'lng' => 'lng',
            'notes' => 'notes',
            'contact_name' => 'contact_name',
            'contact_phone' => 'contact_phone',
            // Item-level request fields
            'items' => 'items',
            'items.order_item_id' => 'order_item_id',
            'items.name' => 'name',
            'items.quantity_ordered' => 'quantity_ordered',
        ];
    }

    /**
     * Get the default callback schema.
     */
    public static function defaultCallbackSchema(): array
    {
        return [
            'external_id' => 'external_id',
            'status' => 'status',
            'completed_at' => 'completed_at',
            'delivered_at' => 'delivered_at', // Alias for Melo ERP
            'recipient_name' => 'recipient_name',
            // Item-level callback fields
            'items' => 'items',
            'items.order_item_id' => 'order_item_id',
            'items.quantity_delivered' => 'quantity_delivered',
            'items.reason' => 'reason',
            'items.notes' => 'notes',
        ];
    }

    /**
     * Get a value from incoming data using the request schema mapping.
     */
    public function getFromRequest(array $data, string $field, mixed $default = null): mixed
    {
        $path = $this->request_schema[$field] ?? $field;

        return data_get($data, $path, $default);
    }

    /**
     * Transform incoming items array from request using schema mapping.
     *
     * @param  array  $data  Incoming destination data that may contain items
     * @return array|null Transformed items array or null if no items
     */
    public function transformItemsFromRequest(array $data): ?array
    {
        $schema = $this->request_schema ?? self::defaultRequestSchema();

        // Get the items array using schema mapping
        $itemsPath = $schema['items'] ?? 'items';
        $items = data_get($data, $itemsPath);

        if (empty($items) || ! is_array($items)) {
            return null;
        }

        // Get field mappings for item properties
        $orderItemIdPath = $schema['items.order_item_id'] ?? 'order_item_id';
        $namePath = $schema['items.name'] ?? 'name';
        $quantityPath = $schema['items.quantity_ordered'] ?? 'quantity_ordered';

        return array_map(function ($item) use ($orderItemIdPath, $namePath, $quantityPath) {
            return [
                'order_item_id' => data_get($item, $orderItemIdPath),
                'name' => data_get($item, $namePath),
                'quantity_ordered' => data_get($item, $quantityPath),
            ];
        }, $items);
    }

    /**
     * Transform destination for callback using the callback schema.
     */
    public function transformForCallback(Destination $destination): array
    {
        $schema = $this->callback_schema ?? self::defaultCallbackSchema();
        $result = [];

        // Transform base fields
        // Schema format: internalField => outputField (what we send in callback)
        // e.g., 'external_id' => 'order_id' means result['order_id'] = destination.external_id
        foreach ($schema as $internalField => $outputField) {
            // Skip item-related fields (handled separately)
            if (str_starts_with($internalField, 'items.') || $internalField === 'items') {
                continue;
            }

            $value = match ($internalField) {
                'external_id' => $destination->external_id,
                'status' => $destination->status->value,
                'completed_at' => $destination->completed_at?->toIso8601String(),
                'delivered_at' => $destination->completed_at?->toIso8601String(), // Alias
                'recipient_name' => $destination->recipient_name,
                'failure_reason' => $destination->failure_reason?->value,
                default => null,
            };

            if ($value !== null) {
                data_set($result, $outputField, $value);
            }
        }

        // Transform items if destination has item tracking
        if ($destination->relationLoaded('items') && $destination->items->isNotEmpty()) {
            $itemsField = $schema['items'] ?? 'items';
            $result[$itemsField] = $this->transformItemsForCallback($destination, $schema);
        }

        return $result;
    }

    /**
     * Transform destination items for callback payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function transformItemsForCallback(Destination $destination, array $schema): array
    {
        // Get field mappings from schema
        $orderItemIdField = $schema['items.order_item_id'] ?? 'order_item_id';
        $quantityField = $schema['items.quantity_delivered'] ?? 'quantity_delivered';
        $reasonField = $schema['items.reason'] ?? 'reason';
        $notesField = $schema['items.notes'] ?? 'notes';

        return $destination->items->map(function ($item) use ($orderItemIdField, $quantityField, $reasonField, $notesField) {
            $itemData = [
                $orderItemIdField => $item->order_item_id,
                $quantityField => $item->quantity_delivered,
            ];

            // Only include reason and notes if there's a discrepancy
            if ($item->hasDiscrepancy()) {
                if ($item->delivery_reason) {
                    $itemData[$reasonField] = $item->delivery_reason->value;
                }
                if ($item->notes) {
                    $itemData[$notesField] = $item->notes;
                }
            }

            return $itemData;
        })->toArray();
    }
}
