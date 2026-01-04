<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status of a driver's trip execution.
 */
enum TripStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::NotStarted => 'لم تبدأ',
            self::InProgress => 'جارية',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }
}
