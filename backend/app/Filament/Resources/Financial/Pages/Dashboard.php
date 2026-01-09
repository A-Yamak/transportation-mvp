<?php

namespace App\Filament\Resources\Financial\Pages;

use App\Filament\Resources\Financial\FinancialResource;
use App\Models\Trip;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;

class Dashboard extends Page
{
    protected static string $resource = FinancialResource::class;

    protected static string $view = 'filament.resources.financial.pages.dashboard';

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

        // Calculate costs from journal entries (5100 = Fuel, 5200 = Driver Payments, 5300 = Maintenance)
        $fuelCosts = JournalEntry::whereIn('account_code', ['5100'])
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('type', 'debit')
            ->sum('amount');

        $driverPayments = JournalEntry::whereIn('account_code', ['5200'])
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('type', 'debit')
            ->sum('amount');

        $maintenanceCosts = JournalEntry::whereIn('account_code', ['5300'])
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('type', 'debit')
            ->sum('amount');

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
}
