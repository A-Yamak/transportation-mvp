<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of an individual delivery destination/stop.
 */
enum DestinationStatus: string
{
    case Pending = 'pending';
    case Arrived = 'arrived';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Arrived => 'Arrived',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Arrived => 'تم الوصول',
            self::Completed => 'مكتمل',
            self::Failed => 'فشل',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Arrived => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
