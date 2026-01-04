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
            'recipient_name' => 'recipient_name',
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
     * Transform destination for callback using the callback schema.
     */
    public function transformForCallback(Destination $destination): array
    {
        $schema = $this->callback_schema ?? self::defaultCallbackSchema();
        $result = [];

        foreach ($schema as $internalField => $externalField) {
            $value = match ($internalField) {
                'external_id' => $destination->external_id,
                'status' => $destination->status->value,
                'completed_at' => $destination->completed_at?->toIso8601String(),
                'recipient_name' => $destination->recipient_name,
                'failure_reason' => $destination->failure_reason?->value,
                default => null,
            };

            if ($value !== null) {
                data_set($result, $externalField, $value);
            }
        }

        return $result;
    }
}
