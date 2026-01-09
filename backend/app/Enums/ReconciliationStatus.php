<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of daily reconciliation.
 */
enum ReconciliationStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Disputed => 'Disputed',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Submitted => 'تم الإرسال',
            self::Acknowledged => 'تم الإقرار',
            self::Disputed => 'معارضة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Submitted => 'blue',
            self::Acknowledged => 'green',
            self::Disputed => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Submitted => 'heroicon-o-paper-airplane',
            self::Acknowledged => 'heroicon-o-check-circle',
            self::Disputed => 'heroicon-o-exclamation-circle',
        };
    }
}
