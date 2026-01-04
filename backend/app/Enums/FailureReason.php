<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reasons for delivery failure at a destination.
 */
enum FailureReason: string
{
    case NotHome = 'not_home';
    case Refused = 'refused';
    case WrongAddress = 'wrong_address';
    case Inaccessible = 'inaccessible';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotHome => 'Customer Not Home',
            self::Refused => 'Refused Delivery',
            self::WrongAddress => 'Wrong Address',
            self::Inaccessible => 'Location Inaccessible',
            self::Other => 'Other',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::NotHome => 'العميل غير موجود',
            self::Refused => 'رفض الاستلام',
            self::WrongAddress => 'عنوان خاطئ',
            self::Inaccessible => 'موقع غير قابل للوصول',
            self::Other => 'سبب آخر',
        };
    }
}
