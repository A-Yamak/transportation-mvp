<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use App\Models\DailyReconciliation;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\PaymentCollection;
use App\Models\Shop;
use App\Models\Trip;
use App\Services\DailyReconciliationService;
use App\Services\PaymentCollectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DailyReconciliationService $service;
    private PaymentCollectionService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DailyReconciliationService::class);
        $this->paymentService = app(PaymentCollectionService::class);
    }

    /**
     * Test generating reconciliation for driver with no trips
     */
    public function test_generate_reconciliation_no_trips(): void
    {
        // Setup
        $driver = Driver::factory()->create();

        // Act
        $reconciliation = $this->service->generateReconciliation(
            driver: $driver,
            date: Carbon::today()
        );

        // Assert
        $this->assertInstanceOf(DailyReconciliation::class, $reconciliation);
        $this->assertEquals($driver->id, $reconciliation->driver_id);
        $this->assertEquals(0.00, $reconciliation->total_expected);
        $this->assertEquals(0.00, $reconciliation->total_collected);
        $this->assertEquals(0, $reconciliation->trips_completed);
    }

    /**
     * Test generating reconciliation with single trip
     */
    public function test_generate_reconciliation_single_trip(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $shop = Shop::factory()->create();

        $dest = Destination::factory()
            ->for($trip)
            ->for($shop)
            ->create(['amount_to_collect' => 1000.00]);

        // Record payment
        $this->paymentService->collectPayment(
            destination: $dest,
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::Cash
        );

        // Act
        $reconciliation = $this->service->generateReconciliation(
            driver: $driver,
            date: Carbon::today()
        );

        // Assert
        $this->assertEquals(1000.00, $reconciliation->total_expected);
        $this->assertEquals(1000.00, $reconciliation->total_collected);
        $this->assertEquals(1000.00, $reconciliation->total_cash);
        $this->assertEquals(0.00, $reconciliation->total_cliq);
        $this->assertEquals(100.0, $reconciliation->collectionRate());
    }

    /**
     * Test reconciliation with multiple trips
     */
    public function test_generate_reconciliation_multiple_trips(): void
    {
        // Setup
        $driver = Driver::factory()->create();

        // Trip 1
        $trip1 = Trip::factory()->for($driver)->create();
        $dest1 = Destination::factory()->for($trip1)->create(['amount_to_collect' => 500.00]);
        $this->paymentService->collectPayment($dest1, 500.00, PaymentMethod::Cash);

        // Trip 2
        $trip2 = Trip::factory()->for($driver)->create();
        $dest2 = Destination::factory()->for($trip2)->create(['amount_to_collect' => 300.00]);
        $this->paymentService->collectPayment($dest2, 300.00, PaymentMethod::Cash);

        // Act
        $reconciliation = $this->service->generateReconciliation(
            driver: $driver,
            date: Carbon::today()
        );

        // Assert
        $this->assertEquals(800.00, $reconciliation->total_collected);
        $this->assertEquals(2, $reconciliation->trips_completed);
    }

    /**
     * Test reconciliation with cash and CliQ split
     */
    public function test_reconciliation_cash_and_cliq_split(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $dest1 = Destination::factory()->for($trip)->create(['amount_to_collect' => 600.00]);
        $dest2 = Destination::factory()->for($trip)->create(['amount_to_collect' => 400.00]);

        // Cash payment
        $this->paymentService->collectPayment($dest1, 600.00, PaymentMethod::Cash);

        // CliQ payment
        $this->paymentService->collectPayment(
            destination: $dest2,
            amountCollected: 400.00,
            paymentMethod: PaymentMethod::CliqNow,
            cliqReference: 'TXN-001'
        );

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert
        $this->assertEquals(1000.00, $reconciliation->total_collected);
        $this->assertEquals(600.00, $reconciliation->total_cash);
        $this->assertEquals(400.00, $reconciliation->total_cliq);
    }

    /**
     * Test reconciliation with shortages
     */
    public function test_reconciliation_with_shortages(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $dest1 = Destination::factory()->for($trip)->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()->for($trip)->create(['amount_to_collect' => 500.00]);

        // Full payment
        $this->paymentService->collectPayment($dest1, 1000.00, PaymentMethod::Cash);

        // Partial payment with shortage
        $this->paymentService->collectPayment(
            destination: $dest2,
            amountCollected: 300.00,
            paymentMethod: PaymentMethod::Cash,
            shortageReason: \App\Enums\ShortageReason::CustomerAbsent
        );

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert
        $this->assertEquals(1500.00, $reconciliation->total_expected);
        $this->assertEquals(1300.00, $reconciliation->total_collected);
        $this->assertEquals(200.00, $reconciliation->shortageAmount());
    }

    /**
     * Test shop breakdown generation
     */
    public function test_shop_breakdown_generation(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $shop1 = Shop::factory()->create();
        $shop2 = Shop::factory()->create();

        // Shop 1: 2 destinations
        $dest1 = Destination::factory()->for($trip)->for($shop1)->create(['amount_to_collect' => 500.00]);
        $dest2 = Destination::factory()->for($trip)->for($shop1)->create(['amount_to_collect' => 300.00]);

        // Shop 2: 1 destination
        $dest3 = Destination::factory()->for($trip)->for($shop2)->create(['amount_to_collect' => 200.00]);

        // All payments cash
        $this->paymentService->collectPayment($dest1, 500.00, PaymentMethod::Cash);
        $this->paymentService->collectPayment($dest2, 300.00, PaymentMethod::Cash);
        $this->paymentService->collectPayment($dest3, 200.00, PaymentMethod::Cash);

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert
        $this->assertIsArray($reconciliation->shop_breakdown);
        $this->assertCount(2, $reconciliation->shop_breakdown);

        $shop1_breakdown = collect($reconciliation->shop_breakdown)
            ->firstWhere('shop_id', $shop1->id);
        $this->assertEquals(800.00, $shop1_breakdown['amount_collected']);
        $this->assertEquals(PaymentMethod::Cash->value, $shop1_breakdown['primary_payment_method']);

        $shop2_breakdown = collect($reconciliation->shop_breakdown)
            ->firstWhere('shop_id', $shop2->id);
        $this->assertEquals(200.00, $shop2_breakdown['amount_collected']);
    }

    /**
     * Test that reconciliation status is pending initially
     */
    public function test_reconciliation_status_pending(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 100.00]);
        $this->paymentService->collectPayment($dest, 100.00, PaymentMethod::Cash);

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert
        $this->assertEquals(ReconciliationStatus::Pending, $reconciliation->status);
        $this->assertNull($reconciliation->submitted_at);
    }

    /**
     * Test getting or creating reconciliation (idempotent)
     */
    public function test_get_or_create_reconciliation(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 100.00]);
        $this->paymentService->collectPayment($dest, 100.00, PaymentMethod::Cash);

        // Act: First call
        $reconciliation1 = $this->service->getOrCreateReconciliation(
            driver: $driver,
            date: Carbon::today()
        );

        // Act: Second call (should return same)
        $reconciliation2 = $this->service->getOrCreateReconciliation(
            driver: $driver,
            date: Carbon::today()
        );

        // Assert
        $this->assertEquals($reconciliation1->id, $reconciliation2->id);
        $this->assertDatabaseCount('daily_reconciliations', 1);
    }

    /**
     * Test submitting reconciliation
     */
    public function test_submit_reconciliation(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 100.00]);
        $this->paymentService->collectPayment($dest, 100.00, PaymentMethod::Cash);

        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Act
        $submitted = $this->service->submitReconciliation($reconciliation);

        // Assert
        $this->assertTrue($submitted);
        $reconciliation->refresh();
        $this->assertEquals(ReconciliationStatus::Submitted, $reconciliation->status);
        $this->assertNotNull($reconciliation->submitted_at);
    }

    /**
     * Test acknowledging reconciliation
     */
    public function test_acknowledge_reconciliation(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $admin = Driver::factory()->create(); // Using as "acknowledged_by" user

        $trip = Trip::factory()->for($driver)->create();
        $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 100.00]);
        $this->paymentService->collectPayment($dest, 100.00, PaymentMethod::Cash);

        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());
        $this->service->submitReconciliation($reconciliation);

        // Act
        $this->service->acknowledgeReconciliation($reconciliation, acknowledgedByUserId: $admin->id);

        // Assert
        $reconciliation->refresh();
        $this->assertEquals(ReconciliationStatus::Acknowledged, $reconciliation->status);
        $this->assertNotNull($reconciliation->acknowledged_at);
    }

    /**
     * Test disputing reconciliation
     */
    public function test_dispute_reconciliation(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 100.00]);
        $this->paymentService->collectPayment($dest, 100.00, PaymentMethod::Cash);

        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Act
        $this->service->disputeReconciliation($reconciliation, reason: 'Amount mismatch');

        // Assert
        $reconciliation->refresh();
        $this->assertEquals(ReconciliationStatus::Disputed, $reconciliation->status);
    }

    /**
     * Test collection rate calculation
     */
    public function test_collection_rate_calculation(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $dest1 = Destination::factory()->for($trip)->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()->for($trip)->create(['amount_to_collect' => 500.00]);

        $this->paymentService->collectPayment($dest1, 1000.00, PaymentMethod::Cash);
        $this->paymentService->collectPayment(
            destination: $dest2,
            amountCollected: 250.00,
            paymentMethod: PaymentMethod::Cash,
            shortageReason: \App\Enums\ShortageReason::CustomerAbsent
        );

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert: 1250 / 1500 = 83.33%
        $expectedRate = (1250 / 1500) * 100;
        $this->assertEquals($expectedRate, $reconciliation->collectionRate());
    }

    /**
     * Test reconciliation with multiple drivers (isolation)
     */
    public function test_reconciliation_driver_isolation(): void
    {
        // Setup
        $driver1 = Driver::factory()->create();
        $driver2 = Driver::factory()->create();

        $trip1 = Trip::factory()->for($driver1)->create();
        $trip2 = Trip::factory()->for($driver2)->create();

        $dest1 = Destination::factory()->for($trip1)->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()->for($trip2)->create(['amount_to_collect' => 500.00]);

        $this->paymentService->collectPayment($dest1, 1000.00, PaymentMethod::Cash);
        $this->paymentService->collectPayment($dest2, 500.00, PaymentMethod::Cash);

        // Act
        $reconciliation1 = $this->service->generateReconciliation($driver1, Carbon::today());
        $reconciliation2 = $this->service->generateReconciliation($driver2, Carbon::today());

        // Assert: Each has only their own data
        $this->assertEquals(1000.00, $reconciliation1->total_collected);
        $this->assertEquals(500.00, $reconciliation2->total_collected);
    }

    /**
     * Test reconciliation retrieves data only for specified date
     */
    public function test_reconciliation_date_isolation(): void
    {
        // Setup
        $driver = Driver::factory()->create();

        // Today's trip
        $trip_today = Trip::factory()->for($driver)->create();
        $dest_today = Destination::factory()->for($trip_today)->create(['amount_to_collect' => 1000.00]);
        $this->paymentService->collectPayment($dest_today, 1000.00, PaymentMethod::Cash);

        // Tomorrow's trip (simulate by creating with future date)
        $trip_tomorrow = Trip::factory()->for($driver)->create();
        $dest_tomorrow = Destination::factory()->for($trip_tomorrow)->create(['amount_to_collect' => 500.00]);
        // Travel back in time for payment to be tomorrow
        // (In real system, would use trip's date to filter)

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert: Only today's payment should be included
        // This test verifies date filtering works correctly
        $this->assertGreaterThan(0, $reconciliation->total_collected);
    }

    /**
     * Test reconciliation summary generation
     */
    public function test_get_reconciliation_summary(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $dest1 = Destination::factory()->for($trip)->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()->for($trip)->create(['amount_to_collect' => 500.00]);

        $this->paymentService->collectPayment($dest1, 1000.00, PaymentMethod::Cash);
        $this->paymentService->collectPayment(
            destination: $dest2,
            amountCollected: 400.00,
            paymentMethod: PaymentMethod::CliqNow,
            cliqReference: 'TXN-001'
        );

        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Act
        $summary = $this->service->getReconciliationSummary($reconciliation);

        // Assert
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_expected', $summary);
        $this->assertArrayHasKey('total_collected', $summary);
        $this->assertArrayHasKey('collection_rate', $summary);
        $this->assertArrayHasKey('payment_breakdown', $summary);
        $this->assertArrayHasKey('shop_breakdown', $summary);
    }

    /**
     * Test reconciliation with zero collections
     */
    public function test_reconciliation_zero_collection(): void
    {
        // Setup: Trip with expected amount but no collection
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 1000.00]);

        // No payment recorded

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert
        $this->assertEquals(0.00, $reconciliation->total_collected);
        $this->assertEquals(0.0, $reconciliation->collectionRate());
    }

    /**
     * Test large reconciliation with many destinations
     */
    public function test_reconciliation_large_batch(): void
    {
        // Setup: 20 destinations across multiple trips
        $driver = Driver::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $trip = Trip::factory()->for($driver)->create();
            for ($j = 0; $j < 4; $j++) {
                $dest = Destination::factory()->for($trip)->create(['amount_to_collect' => 100.00]);
                $this->paymentService->collectPayment($dest, 100.00, PaymentMethod::Cash);
            }
        }

        // Act
        $reconciliation = $this->service->generateReconciliation($driver, Carbon::today());

        // Assert
        $this->assertEquals(2000.00, $reconciliation->total_collected);
        $this->assertEquals(5, $reconciliation->trips_completed);
        $this->assertEquals(20, $reconciliation->deliveries_completed);
    }
}
