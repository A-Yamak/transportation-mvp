<?php

namespace App\Enums;

/**
 * Type of trip execution.
 *
 * - delivery: Standard delivery trip (deliver items to customers/shops)
 * - waste_collection: Waste collection trip (collect expired/returned items from shops)
 */
enum TripType: string
{
    case Delivery = 'delivery';
    case WasteCollection = 'waste_collection';

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::WasteCollection => 'Waste Collection',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Delivery => 'توصيل',
            self::WasteCollection => 'جمع النفايات',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivery => 'blue',
            self::WasteCollection => 'orange',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Delivery => 'shopping-bag',
            self::WasteCollection => 'recycle',
        };
    }
}
