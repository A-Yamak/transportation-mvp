<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Business types determine how delivery requests are received.
 *
 * - bulk_order: Business sends delivery requests via API (ERP integration)
 * - pickup: Driver goes to collect items, then delivers
 */
enum BusinessType: string
{
    case BulkOrder = 'bulk_order';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::BulkOrder => 'Bulk Order',
            self::Pickup => 'Pickup',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::BulkOrder => 'طلب بالجملة',
            self::Pickup => 'استلام',
        };
    }
}
