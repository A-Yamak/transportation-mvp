<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Driver;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Ledger\LedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSystemTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed chart of accounts
        $this->seed(ChartOfAccountsSeeder::class);

        $this->ledgerService = app(LedgerService::class);
    }

    public function test_chart_of_accounts_seeder_creates_all_accounts(): void
    {
        $this->assertDatabaseCount('ledger_accounts', 13);

        $this->assertDatabaseHas('ledger_accounts', [
            'code' => '1100',
            'name' => 'Cash',
        ]);

        $this->assertDatabaseHas('ledger_accounts', [
            'code' => '4100',
            'name' => 'Delivery Revenue',
        ]);
    }

    public function test_can_create_balanced_journal_entry(): void
    {
        $entry = $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Test entry',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 100.00],
            ],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertEquals(100.00, $entry->total_debit);
        $this->assertEquals(100.00, $entry->total_credit);
    }

    public function test_cannot_create_unbalanced_journal_entry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Journal entry must be balanced');

        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Unbalanced entry',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 50.00],
            ],
        ]);
    }

    public function test_can_record_trip_revenue(): void
    {
        $business = Business::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->for($business)->create();
        $trip = Trip::factory()
            ->for($deliveryRequest)
            ->for($driver)
            ->for($vehicle)
            ->create();

        $entry = $this->ledgerService->recordTripRevenue($trip, 150.00);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());

        // Check Accounts Receivable debited
        $arItem = $entry->items()->whereHas('ledgerAccount', function ($q) {
            $q->where('code', '1200');
        })->first();
        $this->assertEquals('debit', $arItem->type);
        $this->assertEquals(150.00, $arItem->amount);

        // Check Revenue credited
        $revenueItem = $entry->items()->whereHas('ledgerAccount', function ($q) {
            $q->where('code', '4100');
        })->first();
        $this->assertEquals('credit', $revenueItem->type);
        $this->assertEquals(150.00, $revenueItem->amount);
    }

    public function test_can_record_fuel_expense(): void
    {
        $entry = $this->ledgerService->recordFuelExpense(50.00, 'Fuel purchase at station XYZ');

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
        $this->assertEquals(50.00, $entry->total_debit);
        $this->assertEquals(50.00, $entry->total_credit);
    }

    public function test_can_record_driver_payment(): void
    {
        $driver = Driver::factory()->create();

        $entry = $this->ledgerService->recordDriverPayment(
            $driver->id,
            100.00,
            'Weekly payment'
        );

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue($entry->isBalanced());
    }

    public function test_can_get_account_balance(): void
    {
        // Create some transactions
        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Initial cash',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 1000.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 1000.00],
            ],
        ]);

        $this->ledgerService->recordFuelExpense(50.00, 'Fuel');

        $cashBalance = $this->ledgerService->getAccountBalance('1100');

        // Cash should be 1000 (initial) - 50 (fuel) = 950
        $this->assertEquals(950.00, $cashBalance);
    }

    public function test_can_get_trial_balance(): void
    {
        // Create some transactions
        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Initial cash',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 1000.00],
                ['account_code' => '3000', 'type' => 'credit', 'amount' => 1000.00],
            ],
        ]);

        $trialBalance = $this->ledgerService->getTrialBalance();

        $this->assertIsArray($trialBalance);
        $this->assertArrayHasKey('accounts', $trialBalance);
        $this->assertArrayHasKey('totals', $trialBalance);

        // Totals must be balanced
        $this->assertEquals(
            $trialBalance['totals']['debit'],
            $trialBalance['totals']['credit']
        );
    }

    public function test_ledger_account_has_correct_normal_balance(): void
    {
        $cash = LedgerAccount::where('code', '1100')->first();
        $this->assertEquals('debit', $cash->normal_balance);

        $revenue = LedgerAccount::where('code', '4100')->first();
        $this->assertEquals('credit', $revenue->normal_balance);
    }

    public function test_journal_entry_generates_unique_entry_numbers(): void
    {
        $entry1 = $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Entry 1',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 100.00],
            ],
        ]);

        $entry2 = $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Entry 2',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 200.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 200.00],
            ],
        ]);

        $this->assertNotEquals($entry1->entry_number, $entry2->entry_number);
        $this->assertStringStartsWith('JE-2026-', $entry1->entry_number);
        $this->assertStringStartsWith('JE-2026-', $entry2->entry_number);
    }

    public function test_cannot_delete_account_with_journal_entries(): void
    {
        $cash = LedgerAccount::where('code', '1100')->first();

        // Create entry using cash account
        $this->ledgerService->createJournalEntry([
            'entry_date' => '2026-01-04',
            'description' => 'Test',
            'items' => [
                ['account_code' => '1100', 'type' => 'debit', 'amount' => 100.00],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => 100.00],
            ],
        ]);

        $this->assertFalse($cash->canBeDeleted());
    }
}
