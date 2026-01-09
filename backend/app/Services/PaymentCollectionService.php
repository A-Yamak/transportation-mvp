<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Destination;
use App\Models\PaymentCollection;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Payment Collection Service
 *
 * Handles payment collection operations for driver deliveries.
 * Tracks cash vs CliQ payments, shortages, and daily totals.
 */
class PaymentCollectionService
{
    /**
     * Record a payment collection for a destination.
     *
     * @param Destination $destination
     * @param float $amountCollected
     * @param string $paymentMethod "cash", "cliq_now", "cliq_later", or "mixed"
     * @param string|null $cliqReference CliQ transaction ID (if CliQ payment)
     * @param string|null $shortageReason Reason for shortage (if applicable)
     * @param string|null $notes Optional notes
     * @return PaymentCollection
     */
    public function collectPayment(
        Destination $destination,
        float $amountCollected,
        string $paymentMethod,
        ?string $cliqReference = null,
        ?string $shortageReason = null,
        ?string $notes = null,
    ): PaymentCollection {
        $amountExpected = (float) $destination->amount_to_collect;

        // Determine payment status based on collected amount
        $paymentStatus = match (true) {
            $amountCollected >= $amountExpected => 'collected',
            $amountCollected > 0 => 'partial',
            default => 'failed',
        };

        // Create payment collection record
        $paymentCollection = PaymentCollection::create([
            'destination_id' => $destination->id,
            'trip_id' => $destination->trip_id,
            'driver_id' => auth()->id(),
            'amount_expected' => $amountExpected,
            'amount_collected' => $amountCollected,
            'payment_method' => $paymentMethod,
            'cliq_reference' => $cliqReference,
            'payment_status' => $paymentStatus,
            'shortage_reason' => $shortageReason,
            'notes' => $notes,
            'collected_at' => now(),
        ]);

        // Update destination payment fields
        $destination->update([
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_reference' => $cliqReference,
            'payment_collected_at' => now(),
            'amount_collected' => $amountCollected,
        ]);

        return $paymentCollection;
    }

    /**
     * Get all payment collections for a trip.
     *
     * @param Trip $trip
     * @return Collection
     */
    public function getPaymentsForTrip(Trip $trip): Collection
    {
        return PaymentCollection::where('trip_id', $trip->id)
            ->with(['destination', 'driver'])
            ->get();
    }

    /**
     * Get daily totals for a driver on a specific date.
     *
     * @param User $driver
     * @param Carbon $date
     * @return array
     */
    public function calculateDailyTotals(User $driver, Carbon $date): array
    {
        $collections = PaymentCollection::where('driver_id', $driver->id)
            ->whereDate('collected_at', $date)
            ->with(['destination.trip'])
            ->get();

        $totalExpected = 0;
        $totalCollected = 0;
        $totalCash = 0;
        $totalCliq = 0;
        $shopBreakdown = [];

        foreach ($collections as $collection) {
            $totalExpected += $collection->amount_expected;
            $totalCollected += $collection->amount_collected;

            if ($collection->isCashPayment()) {
                $totalCash += $collection->amount_collected;
            } else {
                $totalCliq += $collection->amount_collected;
            }

            // Build shop breakdown
            $shopId = $collection->destination->shop_id;
            if (!isset($shopBreakdown[$shopId])) {
                $shopBreakdown[$shopId] = [
                    'shop_id' => $shopId,
                    'amount_collected' => 0,
                    'amount_expected' => 0,
                    'methods' => [],
                ];
            }

            $shopBreakdown[$shopId]['amount_collected'] += $collection->amount_collected;
            $shopBreakdown[$shopId]['amount_expected'] += $collection->amount_expected;

            if (!in_array($collection->payment_method, $shopBreakdown[$shopId]['methods'])) {
                $shopBreakdown[$shopId]['methods'][] = $collection->payment_method;
            }
        }

        return [
            'total_expected' => round($totalExpected, 2),
            'total_collected' => round($totalCollected, 2),
            'total_cash' => round($totalCash, 2),
            'total_cliq' => round($totalCliq, 2),
            'collections_count' => $collections->count(),
            'shop_breakdown' => array_values($shopBreakdown),
            'shortage_amount' => round(max(0, $totalExpected - $totalCollected), 2),
            'overage_amount' => round(max(0, $totalCollected - $totalExpected), 2),
            'collection_rate' => $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 2) : 100,
        ];
    }

    /**
     * Get payment collections with shortages for a driver on a date.
     *
     * @param User $driver
     * @param Carbon $date
     * @return Collection
     */
    public function getShortagesForDriver(User $driver, Carbon $date): Collection
    {
        return PaymentCollection::where('driver_id', $driver->id)
            ->whereDate('collected_at', $date)
            ->whereColumn('amount_collected', '<', 'amount_expected')
            ->with(['destination.shop', 'destination.trip'])
            ->orderByDesc('shortage_amount')
            ->get();
    }

    /**
     * Get collection statistics for a date range.
     *
     * @param User $driver
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getCollectionStats(User $driver, Carbon $startDate, Carbon $endDate): array
    {
        $collections = PaymentCollection::where('driver_id', $driver->id)
            ->whereBetween('collected_at', [$startDate, $endDate])
            ->get();

        $stats = [
            'total_days' => 0,
            'total_collections' => $collections->count(),
            'total_amount_collected' => 0,
            'total_cash' => 0,
            'total_cliq' => 0,
            'collections_with_shortage' => 0,
            'collections_fully_paid' => 0,
            'average_collection_rate' => 0,
        ];

        $daysWithCollections = $collections->groupBy(fn ($c) => $c->collected_at->toDateString());
        $stats['total_days'] = $daysWithCollections->count();

        foreach ($collections as $collection) {
            $stats['total_amount_collected'] += $collection->amount_collected;

            if ($collection->isCashPayment()) {
                $stats['total_cash'] += $collection->amount_collected;
            } else {
                $stats['total_cliq'] += $collection->amount_collected;
            }

            if ($collection->hasShortage()) {
                $stats['collections_with_shortage']++;
            } else {
                $stats['collections_fully_paid']++;
            }
        }

        // Calculate average collection rate
        $totalExpected = $collections->sum('amount_expected');
        if ($totalExpected > 0) {
            $stats['average_collection_rate'] = round(($stats['total_amount_collected'] / $totalExpected) * 100, 2);
        }

        // Round monetary values
        $stats['total_amount_collected'] = round($stats['total_amount_collected'], 2);
        $stats['total_cash'] = round($stats['total_cash'], 2);
        $stats['total_cliq'] = round($stats['total_cliq'], 2);

        return $stats;
    }
}
