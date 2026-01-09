<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Payment method used for cash collection at destination.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case CliqNow = 'cliq_now';
    case CliqLater = 'cliq_later';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::CliqNow => 'CliQ Now',
            self::CliqLater => 'CliQ Later',
            self::Mixed => 'Mixed',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Cash => 'نقد',
            self::CliqNow => 'كليك الآن',
            self::CliqLater => 'كليك لاحقا',
            self::Mixed => 'مختلط',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'green',
            self::CliqNow => 'blue',
            self::CliqLater => 'orange',
            self::Mixed => 'purple',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'heroicon-o-banknotes',
            self::CliqNow => 'heroicon-o-credit-card',
            self::CliqLater => 'heroicon-o-clock',
            self::Mixed => 'heroicon-o-currency-dollar',
        };
    }
}
