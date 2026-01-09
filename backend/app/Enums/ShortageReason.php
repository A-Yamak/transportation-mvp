<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reason for payment shortage (when collected < expected).
 */
enum ShortageReason: string
{
    case CustomerAbsent = 'customer_absent';
    case InsufficientFunds = 'insufficient_funds';
    case CustomerRefused = 'customer_refused';
    case PartialDelivery = 'partial_delivery';
    case DeliveryError = 'delivery_error';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerAbsent => 'Customer Absent',
            self::InsufficientFunds => 'Insufficient Funds',
            self::CustomerRefused => 'Customer Refused',
            self::PartialDelivery => 'Partial Delivery',
            self::DeliveryError => 'Delivery Error',
            self::Other => 'Other Reason',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::CustomerAbsent => 'العميل غير موجود',
            self::InsufficientFunds => 'عدم توفر الأموال',
            self::CustomerRefused => 'رفض العميل',
            self::PartialDelivery => 'تسليم جزئي',
            self::DeliveryError => 'خطأ في التسليم',
            self::Other => 'سبب آخر',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CustomerAbsent => 'heroicon-o-user-minus',
            self::InsufficientFunds => 'heroicon-o-exclamation-triangle',
            self::CustomerRefused => 'heroicon-o-hand-raised',
            self::PartialDelivery => 'heroicon-o-box',
            self::DeliveryError => 'heroicon-o-exclamation-circle',
            self::Other => 'heroicon-o-question-mark-circle',
        };
    }
}
