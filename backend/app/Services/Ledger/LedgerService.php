<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles double-entry accounting operations.
 *
 * All financial transactions must use this service to ensure proper
 * double-entry bookkeeping (debits = credits).
 */
class LedgerService
{
    /**
     * Create a journal entry with balanced debits and credits.
     *
     * @param  array  $data  [
     *                       'entry_date' => '2026-01-04',
     *                       'description' => 'Trip revenue',
     *                       'reference_type' => 'App\Models\Trip',
     *                       'reference_id' => 'uuid',
     *                       'created_by' => 'user-id',
     *                       'items' => [
     *                       ['account_code' => '1200', 'type' => 'debit', 'amount' => 150.00, 'memo' => '...'],
     *                       ['account_code' => '4100', 'type' => 'credit', 'amount' => 150.00, 'memo' => '...'],
     *                       ]
     *                       ]
     * @return JournalEntry
     *
     * @throws InvalidArgumentException if debits != credits
     */
    public function createJournalEntry(array $data): JournalEntry
    {
        // Validate balanced entry
        $totalDebit = collect($data['items'])->where('type', 'debit')->sum('amount');
        $totalCredit = collect($data['items'])->where('type', 'credit')->sum('amount');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(
                "Journal entry must be balanced. Debit: {$totalDebit}, Credit: {$totalCredit}"
            );
        }

        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $account = LedgerAccount::where('code', $item['account_code'])->firstOrFail();

                $entry->items()->create([
                    'ledger_account_id' => $account->id,
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            return $entry->load('items.ledgerAccount');
        });
    }

    /**
     * Get the balance of a specific ledger account.
     *
     * @param  string  $accountCode  Account code (e.g., "1100" for Cash)
     * @param  string|null  $asOfDate  Optional date to calculate balance as of that date
     * @return float Balance (positive for debit balance accounts, negative for credit balance)
     */
    public function getAccountBalance(string $accountCode, ?string $asOfDate = null): float
    {
        $account = LedgerAccount::where('code', $accountCode)->firstOrFail();

        $query = $account->journalEntryItems()
            ->join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id');

        if ($asOfDate) {
            $query->where('journal_entries.entry_date', '<=', $asOfDate);
        }

        $debits = (float) $query->clone()->where('journal_entry_items.type', 'debit')->sum('journal_entry_items.amount');
        $credits = (float) $query->clone()->where('journal_entry_items.type', 'credit')->sum('journal_entry_items.amount');

        // Return based on account's normal balance
        $balance = $debits - $credits;

        return $account->account_type->normalBalance() === 'debit' ? $balance : -$balance;
    }

    /**
     * Get trial balance (all accounts with their balances).
     *
     * @param  string|null  $asOfDate  Optional date
     * @return array [
     *               ['code' => '1100', 'name' => 'Cash', 'debit' => 5000.00, 'credit' => 0],
     *               ['code' => '4100', 'name' => 'Revenue', 'debit' => 0, 'credit' => 5000.00],
     *               'totals' => ['debit' => 5000.00, 'credit' => 5000.00]
     *               ]
     */
    public function getTrialBalance(?string $asOfDate = null): array
    {
        $accounts = LedgerAccount::active()->get();
        $balances = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->code, $asOfDate);

            // For debit accounts: positive balance = debit column, negative = credit column
            // For credit accounts: positive balance = credit column, negative = debit column
            if ($account->account_type->normalBalance() === 'debit') {
                $debit = $balance >= 0 ? $balance : 0;
                $credit = $balance < 0 ? abs($balance) : 0;
            } else {
                $credit = $balance >= 0 ? $balance : 0;
                $debit = $balance < 0 ? abs($balance) : 0;
            }

            $balances[] = [
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type->value,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'accounts' => $balances,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
            ],
        ];
    }

    /**
     * Record trip revenue (when trip is completed and billed).
     *
     * DEBIT  1200 Accounts Receivable (Business)  $amount
     * CREDIT 4100 Delivery Revenue                $amount
     *
     * @param  Trip  $trip
     * @param  float  $amount  Revenue amount
     * @return JournalEntry
     */
    public function recordTripRevenue(Trip $trip, float $amount): JournalEntry
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => "Trip revenue for delivery request {$trip->deliveryRequest->id}",
            'reference_type' => Trip::class,
            'reference_id' => $trip->id,
            'items' => [
                [
                    'account_code' => '1200', // Accounts Receivable
                    'type' => 'debit',
                    'amount' => $amount,
                    'memo' => "Trip {$trip->id} - Business {$trip->deliveryRequest->business->name}",
                ],
                [
                    'account_code' => '4100', // Delivery Revenue
                    'type' => 'credit',
                    'amount' => $amount,
                    'memo' => "Trip {$trip->id}",
                ],
            ],
        ]);
    }

    /**
     * Record fuel expense.
     *
     * DEBIT  5100 Fuel Expense  $amount
     * CREDIT 1100 Cash          $amount
     *
     * @param  float  $amount  Fuel cost
     * @param  string  $description  Fuel purchase description
     * @return JournalEntry
     */
    public function recordFuelExpense(float $amount, string $description): JournalEntry
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'items' => [
                [
                    'account_code' => '5100', // Fuel Expense
                    'type' => 'debit',
                    'amount' => $amount,
                    'memo' => $description,
                ],
                [
                    'account_code' => '1100', // Cash
                    'type' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Fuel payment',
                ],
            ],
        ]);
    }

    /**
     * Record driver payment.
     *
     * DEBIT  5200 Driver Payments  $amount
     * CREDIT 1100 Cash             $amount
     *
     * @param  string  $driverId  Driver UUID
     * @param  float  $amount  Payment amount
     * @param  string  $description  Payment description
     * @return JournalEntry
     */
    public function recordDriverPayment(string $driverId, float $amount, string $description): JournalEntry
    {
        return $this->createJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => $description,
            'items' => [
                [
                    'account_code' => '5200', // Driver Payments
                    'type' => 'debit',
                    'amount' => $amount,
                    'memo' => "Driver {$driverId} - {$description}",
                ],
                [
                    'account_code' => '1100', // Cash
                    'type' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Driver payment',
                ],
            ],
        ]);
    }
}
