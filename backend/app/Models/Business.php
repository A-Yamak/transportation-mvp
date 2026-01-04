<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Business Model
 *
 * Represents a client company that uses our transportation service.
 * Businesses receive delivery requests via our API and get callbacks
 * when deliveries are completed.
 *
 * @property string $id UUID
 * @property string $name Business name
 * @property BusinessType $business_type Type of business (bulk_order, pickup)
 * @property string $api_key API key for authentication
 * @property string|null $callback_url URL for delivery status callbacks
 * @property string|null $callback_api_key API key for authenticating callbacks
 * @property bool $is_active Whether business is active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Business extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'business_type',
        'api_key',
        'callback_url',
        'callback_api_key',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
        'callback_api_key',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Generate a new API key for this business.
     */
    public function regenerateApiKey(): string
    {
        $this->api_key = 'bus_' . Str::random(40);
        $this->save();

        return $this->api_key;
    }

    /**
     * Delivery requests made by this business.
     */
    public function deliveryRequests(): HasMany
    {
        return $this->hasMany(DeliveryRequest::class);
    }

    /**
     * Payload schema for this business (custom API field mapping).
     */
    public function payloadSchema(): HasOne
    {
        return $this->hasOne(BusinessPayloadSchema::class);
    }

    /**
     * Scope for active businesses only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
