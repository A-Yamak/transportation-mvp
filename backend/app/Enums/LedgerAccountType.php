<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Types of accounts in double-entry ledger system.
 */
enum LedgerAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::Expense => 'Expense',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Asset => 'أصول',
            self::Liability => 'خصوم',
            self::Equity => 'حقوق ملكية',
            self::Revenue => 'إيرادات',
            self::Expense => 'مصروفات',
        };
    }

    /**
     * Normal balance side for this account type.
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Revenue => 'credit',
        };
    }
}
