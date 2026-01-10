<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Movement Type Enum
 *
 * Types of tupperware container movements.
 */
enum MovementType: string
{
    case Delivery = 'delivery';
    case Return = 'return';
    case Adjustment = 'adjustment';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Return => 'Return',
            self::Adjustment => 'Adjustment',
        };
    }
}
