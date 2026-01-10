<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShortageReason;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\PaymentCollection;
use App\Models\Shop;
use App\Models\Trip;
use App\Services\PaymentCollectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCollectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentCollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentCollectionService::class);
    }

    /**
     * Test collecting full payment in cash
     */
    public function test_collect_full_payment_cash(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::Cash,
            cliqReference: null,
            shortageReason: null,
            notes: 'Full payment received'
        );

        // Assert
        $this->assertInstanceOf(PaymentCollection::class, $collection);
        $this->assertEquals(1000.00, $collection->amount_collected);
        $this->assertEquals(PaymentMethod::Cash, $collection->payment_method);
        $this->assertEquals(PaymentStatus::Collected, $collection->payment_status);
        $this->assertEquals(0.00, $collection->shortage_amount);
        $this->assertTrue($collection->isFullyCollected());
        $this->assertFalse($collection->hasShortage());

        // Verify destination is updated
        $destination->refresh();
        $this->assertEquals(1000.00, $destination->amount_collected);
        $this->assertEquals(PaymentMethod::Cash, $destination->payment_method);
        $this->assertEquals(PaymentStatus::Collected, $destination->payment_status);
    }

    /**
     * Test collecting partial payment with shortage reason
     */
    public function test_collect_partial_payment_with_reason(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 750.00,
            paymentMethod: PaymentMethod::Cash,
            cliqReference: null,
            shortageReason: ShortageReason::CustomerAbsent,
            notes: 'Customer was not available'
        );

        // Assert
        $this->assertEquals(750.00, $collection->amount_collected);
        $this->assertEquals(PaymentStatus::Partial, $collection->payment_status);
        $this->assertEquals(250.00, $collection->shortage_amount);
        $this->assertTrue($collection->hasShortage());
        $this->assertFalse($collection->isFullyCollected());
        $this->assertEquals(ShortageReason::CustomerAbsent, $collection->shortage_reason);
    }

    /**
     * Test collecting payment via CliQ Now with reference
     */
    public function test_collect_payment_cliq_now(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::CliqNow,
            cliqReference: 'CLIQ-TXN-123456',
            shortageReason: null,
            notes: 'CliQ payment processed'
        );

        // Assert
        $this->assertEquals(PaymentMethod::CliqNow, $collection->payment_method);
        $this->assertEquals('CLIQ-TXN-123456', $collection->cliq_reference);
        $this->assertTrue($collection->isCliQPayment());
        $this->assertFalse($collection->isCashPayment());
    }

    /**
     * Test collecting payment via CliQ Later with reference
     */
    public function test_collect_payment_cliq_later(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::CliqLater,
            cliqReference: 'CLIQ-LATER-789',
            shortageReason: null
        );

        // Assert
        $this->assertEquals(PaymentMethod::CliqLater, $collection->payment_method);
        $this->assertEquals('CLIQ-LATER-789', $collection->cliq_reference);
        $this->assertTrue($collection->isCliQPayment());
    }

    /**
     * Test collecting no payment (failed collection)
     */
    public function test_collect_zero_payment(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 0.00,
            paymentMethod: PaymentMethod::Cash,
            cliqReference: null,
            shortageReason: ShortageReason::CustomerRefused,
            notes: 'Customer refused payment'
        );

        // Assert
        $this->assertEquals(0.00, $collection->amount_collected);
        $this->assertEquals(PaymentStatus::Failed, $collection->payment_status);
        $this->assertEquals(1000.00, $collection->shortage_amount);
    }

    /**
     * Test calculating daily totals by payment method
     */
    public function test_calculate_daily_totals(): void
    {
        // Setup: Create trip with multiple collections at different shops
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $shop1 = Shop::factory()->create();
        $shop2 = Shop::factory()->create();
        $shop3 = Shop::factory()->create();

        $dest1 = Destination::factory()->forTrip($trip)->for($shop1)->create(['amount_to_collect' => 500.00]);
        $dest2 = Destination::factory()->forTrip($trip)->for($shop2)->create(['amount_to_collect' => 700.00]);
        $dest3 = Destination::factory()->forTrip($trip)->for($shop3)->create(['amount_to_collect' => 300.00]);

        // Create payments
        $this->service->collectPayment(
            destination: $dest1,
            amountCollected: 500.00,
            paymentMethod: PaymentMethod::Cash
        );

        $this->service->collectPayment(
            destination: $dest2,
            amountCollected: 700.00,
            paymentMethod: PaymentMethod::CliqNow,
            cliqReference: 'TXN-001'
        );

        $this->service->collectPayment(
            destination: $dest3,
            amountCollected: 250.00,
            paymentMethod: PaymentMethod::Cash,
            shortageReason: ShortageReason::InsufficientFunds
        );

        // Act
        $totals = $this->service->calculateDailyTotals(
            driver: $driver,
            date: Carbon::today()
        );

        // Assert
        $this->assertEquals(1450.00, $totals['total_collected']);
        $this->assertEquals(750.00, $totals['total_cash']); // 500 + 250
        $this->assertEquals(700.00, $totals['total_cliq']);
        $this->assertEquals(50.00, $totals['shortage_amount']);
        $this->assertCount(3, $totals['shop_breakdown']);

        // Check collection rate
        $expectedTotal = 500 + 700 + 300;
        $expectedRate = round(($totals['total_collected'] / $expectedTotal) * 100, 2);
        $this->assertEquals($expectedRate, $totals['collection_rate']);
    }

    /**
     * Test getting payments for a specific trip
     */
    public function test_get_payments_for_trip(): void
    {
        // Setup - create trip first, then destinations
        $trip = Trip::factory()->create();
        $destinations = Destination::factory(3)
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        // Create payments for all destinations
        foreach ($destinations as $destination) {
            $this->service->collectPayment(
                destination: $destination,
                amountCollected: $destination->amount_to_collect * 0.9,
                paymentMethod: PaymentMethod::Cash,
                shortageReason: ShortageReason::CustomerAbsent
            );
        }

        // Act
        $payments = $this->service->getPaymentsForTrip($trip);

        // Assert
        $this->assertCount(3, $payments);
        $this->assertTrue($payments->every(fn($p) => $p->trip_id === $trip->id));
    }

    /**
     * Test getting shortages for a date range
     */
    public function test_get_shortages_for_driver(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $dest1 = Destination::factory()->forTrip($trip)->create(['amount_to_collect' => 1000.00]);
        $dest2 = Destination::factory()->forTrip($trip)->create(['amount_to_collect' => 500.00]);

        // Full payment (no shortage)
        $this->service->collectPayment(
            destination: $dest1,
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::Cash
        );

        // Partial payment (with shortage)
        $this->service->collectPayment(
            destination: $dest2,
            amountCollected: 300.00,
            paymentMethod: PaymentMethod::Cash,
            shortageReason: ShortageReason::InsufficientFunds
        );

        // Act
        $shortages = $this->service->getShortagesForDriver(
            driver: $driver,
            date: Carbon::today()
        );

        // Assert
        $this->assertCount(1, $shortages);
        $this->assertEquals(200.00, $shortages->first()->shortage_amount);
    }

    /**
     * Test collection statistics for date range
     */
    public function test_get_collection_stats(): void
    {
        // Setup
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->for($driver)->create();

        $destinations = Destination::factory(5)->forTrip($trip)->create([
            'amount_to_collect' => 1000.00
        ]);

        // Mix of successful and partial collections
        $this->service->collectPayment(
            destination: $destinations[0],
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::Cash
        );

        $this->service->collectPayment(
            destination: $destinations[1],
            amountCollected: 900.00,
            paymentMethod: PaymentMethod::Cash,
            shortageReason: ShortageReason::CustomerAbsent
        );

        $this->service->collectPayment(
            destination: $destinations[2],
            amountCollected: 800.00,
            paymentMethod: PaymentMethod::CliqNow,
            cliqReference: 'TXN-001'
        );

        // Act
        $stats = $this->service->getCollectionStats(
            driver: $driver,
            startDate: Carbon::today(),
            endDate: Carbon::today()
        );

        // Assert
        $this->assertEquals(2700.00, $stats['total_amount_collected']);
        $this->assertEquals(1900.00, $stats['total_cash']);
        $this->assertEquals(800.00, $stats['total_cliq']);
        $this->assertEquals(2, $stats['collections_with_shortage']); // dest2 and dest3 are partial
        $this->assertTrue($stats['average_collection_rate'] > 0);
    }

    /**
     * Test that collected_at timestamp is set
     */
    public function test_collected_at_timestamp_is_set(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1000.00]);

        $before = Carbon::now()->subSecond();

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 1000.00,
            paymentMethod: PaymentMethod::Cash
        );

        $after = Carbon::now()->addSecond();

        // Assert
        $this->assertNotNull($collection->collected_at);
        $this->assertTrue(
            $collection->collected_at->greaterThanOrEqualTo($before) &&
            $collection->collected_at->lessThanOrEqualTo($after),
            "collected_at should be within reasonable time bounds"
        );
    }

    /**
     * Test decimal precision for amount calculations
     */
    public function test_decimal_precision_calculations(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 1234.56]);

        // Act
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 987.33,
            paymentMethod: PaymentMethod::Cash,
            shortageReason: ShortageReason::Other
        );

        // Assert - Verify precision
        $this->assertEquals('987.33', number_format($collection->amount_collected, 2));
        $this->assertEquals('247.23', number_format($collection->shortage_amount, 2));
        $this->assertEquals(987.33 + 247.23, 1234.56);
    }

    /**
     * Test collecting more than expected (overage scenario)
     */
    public function test_collect_more_than_expected(): void
    {
        // Setup
        $trip = Trip::factory()->create();
        $destination = Destination::factory()
            ->forTrip($trip)
            ->create(['amount_to_collect' => 500.00]);

        // Act - Some drivers might collect more (tips, advance payment)
        $collection = $this->service->collectPayment(
            destination: $destination,
            amountCollected: 550.00,
            paymentMethod: PaymentMethod::Cash,
            notes: 'Additional tip received'
        );

        // Assert
        $this->assertEquals(550.00, $collection->amount_collected);
        $this->assertEquals(PaymentStatus::Collected, $collection->payment_status);
        $this->assertFalse($collection->hasShortage());
    }
}
