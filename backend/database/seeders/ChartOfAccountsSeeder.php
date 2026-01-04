<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * Seeds the Chart of Accounts for the transportation business.
 *
 * Follows standard accounting structure:
 * 1000 - Assets
 * 2000 - Liabilities
 * 3000 - Equity
 * 4000 - Revenue
 * 5000 - Expenses
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1000-1999)
            [
                'code' => '1000',
                'name' => 'Assets',
                'account_type' => LedgerAccountType::Asset,
                'description' => 'All company assets',
            ],
            [
                'code' => '1100',
                'name' => 'Cash',
                'account_type' => LedgerAccountType::Asset,
                'parent_code' => '1000',
                'description' => 'Cash on hand and in bank',
            ],
            [
                'code' => '1200',
                'name' => 'Accounts Receivable',
                'account_type' => LedgerAccountType::Asset,
                'parent_code' => '1000',
                'description' => 'Money owed by businesses for completed deliveries',
            ],
            [
                'code' => '1400',
                'name' => 'Vehicles',
                'account_type' => LedgerAccountType::Asset,
                'parent_code' => '1000',
                'description' => 'Company vehicles (VW Caddy, etc.)',
            ],

            // Liabilities (2000-2999)
            [
                'code' => '2000',
                'name' => 'Liabilities',
                'account_type' => LedgerAccountType::Liability,
                'description' => 'All company liabilities',
            ],
            [
                'code' => '2100',
                'name' => 'Accounts Payable',
                'account_type' => LedgerAccountType::Liability,
                'parent_code' => '2000',
                'description' => 'Money owed to suppliers and vendors',
            ],

            // Equity (3000-3999)
            [
                'code' => '3000',
                'name' => 'Equity',
                'account_type' => LedgerAccountType::Equity,
                'description' => 'Owner equity',
            ],

            // Revenue (4000-4999)
            [
                'code' => '4000',
                'name' => 'Revenue',
                'account_type' => LedgerAccountType::Revenue,
                'description' => 'All company revenue',
            ],
            [
                'code' => '4100',
                'name' => 'Delivery Revenue',
                'account_type' => LedgerAccountType::Revenue,
                'parent_code' => '4000',
                'description' => 'Revenue from delivery services',
            ],

            // Expenses (5000-5999)
            [
                'code' => '5000',
                'name' => 'Expenses',
                'account_type' => LedgerAccountType::Expense,
                'description' => 'All company expenses',
            ],
            [
                'code' => '5100',
                'name' => 'Fuel Expense',
                'account_type' => LedgerAccountType::Expense,
                'parent_code' => '5000',
                'description' => 'Fuel costs for vehicles',
            ],
            [
                'code' => '5200',
                'name' => 'Driver Payments',
                'account_type' => LedgerAccountType::Expense,
                'parent_code' => '5000',
                'description' => 'Payments to drivers',
            ],
            [
                'code' => '5300',
                'name' => 'Vehicle Maintenance',
                'account_type' => LedgerAccountType::Expense,
                'parent_code' => '5000',
                'description' => 'Vehicle repairs and maintenance',
            ],
        ];

        foreach ($accounts as $accountData) {
            $parentCode = $accountData['parent_code'] ?? null;
            unset($accountData['parent_code']);

            $account = LedgerAccount::create($accountData);

            if ($parentCode) {
                $parent = LedgerAccount::where('code', $parentCode)->first();
                if ($parent) {
                    $account->update(['parent_account_id' => $parent->id]);
                }
            }
        }

        $this->command->info('Chart of Accounts created successfully.');
    }
}
