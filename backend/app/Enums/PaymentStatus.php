<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of payment collection at destination.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Collected => 'Collected',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Collected => 'تم التحصيل',
            self::Partial => 'جزئي',
            self::Failed => 'فشل',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Collected => 'green',
            self::Partial => 'orange',
            self::Failed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Collected => 'heroicon-o-check-circle',
            self::Partial => 'heroicon-o-exclamation-circle',
            self::Failed => 'heroicon-o-x-circle',
        };
    }
}
