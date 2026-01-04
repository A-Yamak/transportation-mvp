<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a delivery request from a business client.
 */
enum DeliveryRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Accepted => 'مقبول',
            self::InProgress => 'جاري التنفيذ',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغى',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Accepted => 'blue',
            self::InProgress => 'indigo',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }
}
