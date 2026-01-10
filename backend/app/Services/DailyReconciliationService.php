<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\DailyReconciliation;
use App\Models\Driver;
use App\Models\PaymentCollection;
use App\Models\Trip;
use App\Services\Callback\ReconciliationCallbackService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Daily Reconciliation Service
 *
 * Generates end-of-day reconciliation summaries for drivers.
 * Calculates totals, shop breakdown, and prepares for submission to Melo ERP.
 */
class DailyReconciliationService
{
    public function __construct(
        protected ReconciliationCallbackService $callbackService,
    ) {}
    /**
     * Generate daily reconciliation for a driver on a specific date.
     *
     * @param Driver $driver
     * @param Carbon $date
     * @param string|null $businessId
     * @return DailyReconciliation
     */
    public function generateReconciliation(Driver $driver, Carbon $date, ?string $businessId = null): DailyReconciliation
    {
        // Get all trips for driver on date (started or completed on this date)
        $trips = Trip::where('driver_id', $driver->id)
            ->where(function ($query) use ($date) {
                $query->whereDate('started_at', $date)
                    ->orWhereDate('completed_at', $date);
            })
            ->with(['deliveryRequest'])
            ->get();

        // Get all payment collections for the date
        $paymentCollections = PaymentCollection::where('driver_id', $driver->id)
            ->whereDate('collected_at', $date)
            ->with(['destination.shop'])
            ->get();

        // Calculate totals
        $totalExpected = $paymentCollections->sum('amount_expected');
        $totalCollected = $paymentCollections->sum('amount_collected');

        // Split by payment method
        $totalCash = $paymentCollections
            ->where('payment_method', PaymentMethod::Cash)
            ->sum('amount_collected');

        $totalCliq = $paymentCollections
            ->filter(fn ($pc) => in_array($pc->payment_method, [PaymentMethod::CliqNow, PaymentMethod::CliqLater]))
            ->sum('amount_collected');

        // Count trips and deliveries
        $tripsCompleted = $trips->filter(fn ($t) => $t->completed_at !== null)->count();
        $deliveriesCompleted = $paymentCollections->count();

        // Calculate total KM from trips
        $totalKmDriven = $trips->sum(function ($trip) {
            return $trip->actual_km_driven ?? 0;
        });

        // Build per-shop breakdown
        $shopBreakdown = $this->buildShopBreakdown($paymentCollections);

        // Get business_id from the first trip (drivers can work for multiple businesses)
        $derivedBusinessId = $businessId ?? $trips->first()?->deliveryRequest?->business_id;

        // If no business_id can be derived, use a fallback from payment collections
        if (!$derivedBusinessId && $paymentCollections->isNotEmpty()) {
            $derivedBusinessId = $paymentCollections->first()?->destination?->deliveryRequest?->business_id;
        }

        // Create reconciliation record
        $reconciliation = DailyReconciliation::create([
            'driver_id' => $driver->id,
            'business_id' => $derivedBusinessId,
            'reconciliation_date' => $date,
            'total_expected' => round($totalExpected, 2),
            'total_collected' => round($totalCollected, 2),
            'total_cash' => round($totalCash, 2),
            'total_cliq' => round($totalCliq, 2),
            'trips_completed' => $tripsCompleted,
            'deliveries_completed' => $deliveriesCompleted,
            'total_km_driven' => round($totalKmDriven, 2),
            'status' => 'pending',
            'shop_breakdown' => $shopBreakdown,
        ]);

        return $reconciliation;
    }

    /**
     * Build per-shop breakdown for reconciliation.
     *
     * @param Collection $paymentCollections
     * @return array
     */
    private function buildShopBreakdown(Collection $paymentCollections): array
    {
        $shopGroups = $paymentCollections->groupBy(fn ($pc) => $pc->destination->shop_id);

        return $shopGroups->map(function ($collections, $shopId) {
            $totalCollected = $collections->sum('amount_collected');
            $methods = $collections->pluck('payment_method')->unique()->values();

            // Determine primary method
            $cashCollections = $collections->where('payment_method', PaymentMethod::Cash)->count();
            $cliqCollections = $collections->filter(
                fn ($c) => in_array($c->payment_method, [PaymentMethod::CliqNow, PaymentMethod::CliqLater])
            )->count();

            $primaryMethod = $cashCollections > $cliqCollections ? 'cash' : 'cliq';
            if ($cashCollections > 0 && $cliqCollections > 0) {
                $primaryMethod = 'mixed';
            }

            return [
                'shop_id' => $shopId,
                'shop_name' => $collections->first()?->destination?->shop?->name ?? 'Unknown Shop',
                'amount_collected' => round($totalCollected, 2),
                'amount_expected' => round($collections->sum('amount_expected'), 2),
                'payment_method' => $primaryMethod,
                'payment_methods' => $methods->toArray(),
                'deliveries_count' => $collections->count(),
            ];
        })->values()->toArray();
    }

    /**
     * Get or create reconciliation for a driver on a date.
     *
     * @param Driver $driver
     * @param Carbon $date
     * @return DailyReconciliation
     */
    public function getOrCreateReconciliation(Driver $driver, Carbon $date): DailyReconciliation
    {
        $existing = DailyReconciliation::where('driver_id', $driver->id)
            ->where('reconciliation_date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->generateReconciliation($driver, $date);
    }

    /**
     * Submit reconciliation to Melo ERP (via callback).
     *
     * @param DailyReconciliation $reconciliation
     * @param array $context Additional context for callback
     * @return bool
     */
    public function submitReconciliation(DailyReconciliation $reconciliation, array $context = []): bool
    {
        // Mark as submitted
        $reconciliation->markAsSubmitted();

        // Send callback to Melo ERP (async via queue)
        $this->callbackService->sendReconciliationCallback($reconciliation);

        return true;
    }

    /**
     * Get reconciliation summary for display.
     *
     * @param DailyReconciliation $reconciliation
     * @return array
     */
    public function getReconciliationSummary(DailyReconciliation $reconciliation): array
    {
        return [
            'date' => $reconciliation->reconciliation_date->toDateString(),
            'driver' => [
                'id' => $reconciliation->driver->id,
                'name' => $reconciliation->driver->name,
            ],
            'summary' => [
                'total_expected' => $reconciliation->total_expected,
                'total_collected' => $reconciliation->total_collected,
                'shortage_amount' => $reconciliation->shortage_amount,
                'overage_amount' => $reconciliation->overage_amount,
                'collection_rate' => $reconciliation->collection_rate . '%',
                'cash_percentage' => $reconciliation->cash_percentage . '%',
                'cliq_percentage' => $reconciliation->cliq_percentage . '%',
            ],
            'metrics' => [
                'trips_completed' => $reconciliation->trips_completed,
                'deliveries_completed' => $reconciliation->deliveries_completed,
                'total_km_driven' => $reconciliation->total_km_driven,
            ],
            'breakdown' => $reconciliation->shop_breakdown,
            'status' => $reconciliation->status,
        ];
    }

    /**
     * Get all reconciliations for a driver in date range.
     *
     * @param Driver $driver
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection
     */
    public function getReconciliationHistory(Driver $driver, Carbon $startDate, Carbon $endDate): Collection
    {
        return DailyReconciliation::where('driver_id', $driver->id)
            ->whereBetween('reconciliation_date', [$startDate, $endDate])
            ->orderByDesc('reconciliation_date')
            ->get();
    }

    /**
     * Calculate cumulative statistics for a date range.
     *
     * @param Driver $driver
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getCumulativeStats(Driver $driver, Carbon $startDate, Carbon $endDate): array
    {
        $reconciliations = $this->getReconciliationHistory($driver, $startDate, $endDate);

        return [
            'date_range' => $startDate->toDateString() . ' to ' . $endDate->toDateString(),
            'reconciliation_count' => $reconciliations->count(),
            'total_expected' => round($reconciliations->sum('total_expected'), 2),
            'total_collected' => round($reconciliations->sum('total_collected'), 2),
            'total_cash' => round($reconciliations->sum('total_cash'), 2),
            'total_cliq' => round($reconciliations->sum('total_cliq'), 2),
            'total_trips' => $reconciliations->sum('trips_completed'),
            'total_deliveries' => $reconciliations->sum('deliveries_completed'),
            'total_km_driven' => round($reconciliations->sum('total_km_driven'), 2),
            'average_collection_rate' => $this->calculateAverageRate($reconciliations),
            'reconciliations_submitted' => $reconciliations->where('status', '!=', 'pending')->count(),
            'reconciliations_acknowledged' => $reconciliations->where('status', 'acknowledged')->count(),
        ];
    }

    /**
     * Calculate average collection rate across multiple reconciliations.
     *
     * @param Collection $reconciliations
     * @return float
     */
    private function calculateAverageRate(Collection $reconciliations): float
    {
        if ($reconciliations->isEmpty()) {
            return 0;
        }

        $totalExpected = $reconciliations->sum('total_expected');
        if ($totalExpected == 0) {
            return 100;
        }

        $totalCollected = $reconciliations->sum('total_collected');
        return round(($totalCollected / $totalExpected) * 100, 2);
    }

    /**
     * Mark reconciliation as acknowledged by admin.
     *
     * @param DailyReconciliation $reconciliation
     * @return void
     */
    public function acknowledgeReconciliation(DailyReconciliation $reconciliation): void
    {
        $reconciliation->markAsAcknowledged();
    }

    /**
     * Mark reconciliation as disputed.
     *
     * @param DailyReconciliation $reconciliation
     * @return void
     */
    public function disputeReconciliation(DailyReconciliation $reconciliation): void
    {
        $reconciliation->markAsDisputed();
    }
}
