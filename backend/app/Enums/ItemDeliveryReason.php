<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reasons for item-level delivery discrepancies.
 *
 * Used when quantity_delivered differs from quantity_ordered.
 */
enum ItemDeliveryReason: string
{
    case DamagedInTransit = 'damaged_in_transit';
    case CustomerRefused = 'customer_refused';
    case QualityIssue = 'quality_issue';
    case WrongProduct = 'wrong_product';
    case Shortage = 'shortage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DamagedInTransit => 'Damaged in Transit',
            self::CustomerRefused => 'Customer Refused',
            self::QualityIssue => 'Quality Issue',
            self::WrongProduct => 'Wrong Product',
            self::Shortage => 'Shortage',
            self::Other => 'Other',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::DamagedInTransit => 'تلف أثناء النقل',
            self::CustomerRefused => 'رفض العميل',
            self::QualityIssue => 'مشكلة جودة',
            self::WrongProduct => 'منتج خاطئ',
            self::Shortage => 'نقص',
            self::Other => 'سبب آخر',
        };
    }
}
