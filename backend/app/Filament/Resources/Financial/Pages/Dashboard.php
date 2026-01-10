<?php

namespace App\Filament\Resources\Financial\Pages;

use App\Filament\Resources\Financial\FinancialResource;
use App\Models\Trip;
use App\Models\JournalEntryItem;
use App\Models\LedgerAccount;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;

class Dashboard extends Page
{
    protected static string $resource = FinancialResource::class;

    protected string $view = 'filament.resources.financial.pages.dashboard';

    protected ?string $heading = 'Financial Dashboard';

    protected ?string $subHeading = 'Revenue, Costs & Profitability Overview';

    public function getViewData(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        // Calculate revenue
        $completedTrips = Trip::whereBetween('completed_at', [$monthStart, $monthEnd])
            ->where('status', 'completed')
            ->get();

        $totalRevenue = $completedTrips->sum('estimated_cost');
        $completedTripsCount = $completedTrips->count();
        $averageRevenuePerTrip = $completedTripsCount > 0 ? $totalRevenue / $completedTripsCount : 0;

        // Calculate costs from journal entry items
        // 5100 = Fuel, 5200 = Driver Payments, 5300 = Maintenance
        $fuelCosts = $this->getAccountCosts(['5100'], $monthStart, $monthEnd);
        $driverPayments = $this->getAccountCosts(['5200'], $monthStart, $monthEnd);
        $maintenanceCosts = $this->getAccountCosts(['5300'], $monthStart, $monthEnd);

        $totalCosts = $fuelCosts + $driverPayments + $maintenanceCosts;

        // Calculate profitability
        $netProfit = $totalRevenue - $totalCosts;
        $profitMarginPercentage = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
        $costRatioPercentage = $totalRevenue > 0 ? ($totalCosts / $totalRevenue) * 100 : 0;

        // KM metrics
        $totalKmDriven = $completedTrips->sum('actual_km') ?: $completedTrips->sum('total_km');
        $averageKmPerTrip = $completedTripsCount > 0 ? $totalKmDriven / $completedTripsCount : 0;
        $revenuePerKm = $totalKmDriven > 0 ? $totalRevenue / $totalKmDriven : 0;

        return [
            'totalRevenue' => $totalRevenue,
            'completedTripsCount' => $completedTripsCount,
            'averageRevenuePerTrip' => $averageRevenuePerTrip,
            'fuelCosts' => $fuelCosts,
            'driverPayments' => $driverPayments,
            'maintenanceCosts' => $maintenanceCosts,
            'totalCosts' => $totalCosts,
            'netProfit' => $netProfit,
            'profitMarginPercentage' => $profitMarginPercentage,
            'costRatioPercentage' => $costRatioPercentage,
            'totalKmDriven' => $totalKmDriven,
            'averageKmPerTrip' => $averageKmPerTrip,
            'revenuePerKm' => $revenuePerKm,
            'period' => $now->format('F Y'),
        ];
    }

    /**
     * Get total costs for given account codes within a date range.
     */
    private function getAccountCosts(array $accountCodes, Carbon $startDate, Carbon $endDate): float
    {
        // Get ledger account IDs for the given codes
        $accountIds = LedgerAccount::whereIn('code', $accountCodes)->pluck('id');

        if ($accountIds->isEmpty()) {
            return 0;
        }

        // Sum debit amounts from journal entry items
        return (float) JournalEntryItem::whereIn('ledger_account_id', $accountIds)
            ->where('type', 'debit')
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('entry_date', [$startDate, $endDate]);
            })
            ->sum('amount');
    }
}
