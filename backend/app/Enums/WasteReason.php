<?php

namespace App\Enums;

/**
 * Reason why items were classified as waste.
 *
 * - expired: Items exceeded their shelf life date
 * - damaged: Items damaged during storage or transport
 * - returned: Customer returned items
 * - other: Other reasons (specified in notes field)
 */
enum WasteReason: string
{
    case Expired = 'expired';
    case Damaged = 'damaged';
    case Returned = 'returned';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Expired => 'Expired',
            self::Damaged => 'Damaged',
            self::Returned => 'Returned',
            self::Other => 'Other',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Expired => 'منتهي الصلاحية',
            self::Damaged => 'تالف',
            self::Returned => 'مرتجع',
            self::Other => 'أخرى',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Expired => 'red',
            self::Damaged => 'orange',
            self::Returned => 'blue',
            self::Other => 'gray',
        };
    }
}
