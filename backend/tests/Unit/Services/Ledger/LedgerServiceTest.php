<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ledger;

use App\Services\Ledger\LedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
        $this->service = app(LedgerService::class);
    }

    public function test_validates_balanced_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Unbalanced',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 99.00],
            ],
        ]);
    }

    public function test_calculates_account_balance_correctly(): void
    {
        // Initial cash: 1000 debit
        $this->service->createJournalEntry([
            'entry_date' => '2026-01-01',
            'description' => 'Initial',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 1000.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 1000.00],
            ],
        ]);

        // Fuel expense: 50 credit to cash
        $this->service->createJournalEntry([
            'entry_date' => '2026-01-02',
            'description' => 'Fuel',
            'items' => [
                ['account_code' => '5100', 'type' => 'debit', 'amount' => 50.00],
                ['account_code' => '1100', 'type' => 'credit', 'amount' => 50.00],
            ],
        ]);

        $balance = $this->service->getAccountBalance('1100');
        $this->assertEquals(950.00, $balance);
    }

    public function test_trial_balance_is_always_balanced(): void
    {
        // Create multiple entries
        $this->service->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Entry 1',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 500.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 500.00],
            ],
        ]);

        $this->service->recordFuelExpense(50.00, 'Fuel');

        $trialBalance = $this->service->getTrialBalance();

        $this->assertEquals(
            $trialBalance['totals']['debit'],
            $trialBalance['totals']['credit']
        );
    }
}
