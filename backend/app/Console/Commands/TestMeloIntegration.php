<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TripStatus;
use App\Models\Business;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Integration test for Melo ERP flow.
 *
 * Simulates the complete flow:
 * 1. ERP submits delivery request
 * 2. Trip is assigned to driver
 * 3. Driver starts trip
 * 4. Driver arrives at destinations
 * 5. Driver completes deliveries (triggers callback)
 *
 * Usage: php artisan test:melo-integration
 */
class TestMeloIntegration extends Command
{
    protected $signature = 'test:melo-integration
                            {--webhook-url=http://host.docker.internal:9999 : URL for callback receiver}
                            {--cleanup : Delete test data after running}';

    protected $description = 'Run full Melo ERP integration test';

    private string $baseUrl;
    private ?string $apiKey = null;
    private ?string $driverToken = null;

    public function handle(): int
    {
        $this->baseUrl = config('app.url') . '/api/v1';

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║         MELO ERP INTEGRATION TEST                          ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        try {
            // Step 0: Setup
            $this->step0_setup();

            // Step 1: ERP submits delivery request
            $deliveryRequest = $this->step1_submitDeliveryRequest();

            // Step 2: Admin assigns trip to driver
            $trip = $this->step2_assignTrip($deliveryRequest);

            // Step 3: Driver logs in
            $this->step3_driverLogin();

            // Step 4: Driver starts trip
            $this->step4_startTrip($trip);

            // Step 5: Driver completes destinations (triggers callbacks)
            $this->step5_completeDestinations($trip);

            // Step 6: Summary
            $this->step6_summary($trip);

            if ($this->option('cleanup')) {
                $this->cleanup($deliveryRequest, $trip);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('');
            $this->error('TEST FAILED: ' . $e->getMessage());
            $this->error('');
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function step0_setup(): void
    {
        $this->info('STEP 0: Setup');
        $this->line('─────────────────────────────────────────');

        // Get Melo business
        $melo = Business::where('name', 'Melo Group')->first();
        if (!$melo) {
            $this->error('Melo Group business not found. Run: php artisan db:seed --class=MvpSeeder');
            throw new \RuntimeException('Missing test data');
        }

        $this->apiKey = $melo->api_key;
        $this->info("  ✓ Found Melo Group (API Key: {$this->apiKey})");

        // Update callback URL to local receiver
        $webhookUrl = $this->option('webhook-url');
        $melo->update(['callback_url' => $webhookUrl . '/api/delivery-callback']);
        $this->info("  ✓ Updated callback URL to: {$webhookUrl}/api/delivery-callback");

        // Check driver exists
        $driver = Driver::whereHas('user', fn($q) => $q->where('email', 'driver@alsabiqoon.com'))->first();
        if (!$driver) {
            $this->error('Test driver not found. Run: php artisan db:seed --class=MvpSeeder');
            throw new \RuntimeException('Missing test data');
        }
        $this->info("  ✓ Found test driver: {$driver->user->name}");

        $this->newLine();
    }

    private function step1_submitDeliveryRequest(): DeliveryRequest
    {
        $this->info('STEP 1: ERP Submits Delivery Request');
        $this->line('─────────────────────────────────────────');

        // Simulate what Melo ERP would send (using their field names)
        $erpPayload = [
            'destinations' => [
                [
                    'order_id' => 'MELO-' . date('Ymd') . '-001',
                    'delivery_address' => 'Rainbow Street 45, Jabal Amman',
                    'coordinates' => [
                        'latitude' => 31.9515,
                        'longitude' => 35.9239,
                    ],
                    'customer_name' => 'Al-Quds Sweets',
                    'customer_phone' => '+962791234567',
                    'delivery_notes' => 'Back entrance, ask for Abu Ahmad',
                    // Items using Melo's field names (sku, product_name, qty)
                    'items' => [
                        ['sku' => 'CHOC-001', 'product_name' => 'Premium Chocolate Box', 'qty' => 10],
                        ['sku' => 'CAKE-002', 'product_name' => 'Birthday Cake Assortment', 'qty' => 5],
                    ],
                ],
                [
                    'order_id' => 'MELO-' . date('Ymd') . '-002',
                    'delivery_address' => 'Mecca Street 123, Abdoun',
                    'coordinates' => [
                        'latitude' => 31.9544,
                        'longitude' => 35.8789,
                    ],
                    'customer_name' => 'Sweet Palace',
                    'customer_phone' => '+962797654321',
                    'delivery_notes' => 'Morning delivery preferred',
                    // Items using Melo's field names
                    'items' => [
                        ['sku' => 'PAST-003', 'product_name' => 'Baklava Selection', 'qty' => 20],
                        ['sku' => 'COOK-004', 'product_name' => 'Butter Cookies', 'qty' => 15],
                    ],
                ],
            ],
        ];

        $this->line('  Sending to: POST /api/v1/delivery-requests');
        $this->line('  Payload (Melo format):');
        $this->line('  ' . json_encode($erpPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->newLine();

        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/delivery-requests', $erpPayload);

        if (!$response->successful()) {
            $this->error('  ✗ Failed: ' . $response->body());
            throw new \RuntimeException('Delivery request creation failed');
        }

        $data = $response->json('data');
        $this->info("  ✓ Created DeliveryRequest: {$data['id']}");
        $this->info("  ✓ Status: {$data['status']}");
        $this->info("  ✓ Destinations: " . count($data['destinations']));

        $this->newLine();

        return DeliveryRequest::find($data['id']);
    }

    private function step2_assignTrip(DeliveryRequest $deliveryRequest): Trip
    {
        $this->info('STEP 2: Admin Assigns Trip to Driver');
        $this->line('─────────────────────────────────────────');

        $driver = Driver::whereHas('user', fn($q) => $q->where('email', 'driver@alsabiqoon.com'))->first();

        // Create trip directly (simulating admin action)
        $trip = Trip::create([
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $driver->vehicle_id,
            'status' => TripStatus::NotStarted,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->info("  ✓ Created Trip: {$trip->id}");
        $this->info("  ✓ Assigned to: {$driver->user->name}");
        $this->info("  ✓ Vehicle: {$driver->vehicle->full_name}");

        $this->newLine();

        return $trip;
    }

    private function step3_driverLogin(): void
    {
        $this->info('STEP 3: Driver Logs In');
        $this->line('─────────────────────────────────────────');

        $response = Http::post($this->baseUrl . '/auth/login', [
            'email' => 'driver@alsabiqoon.com',
            'password' => 'driver123',
        ]);

        if (!$response->successful()) {
            $this->error('  ✗ Login failed: ' . $response->body());
            throw new \RuntimeException('Driver login failed');
        }

        $this->driverToken = $response->json('access_token');
        $this->info("  ✓ Driver logged in successfully");
        $this->info("  ✓ Token: " . substr($this->driverToken, 0, 20) . '...');

        $this->newLine();
    }

    private function step4_startTrip(Trip $trip): void
    {
        $this->info('STEP 4: Driver Starts Trip');
        $this->line('─────────────────────────────────────────');

        $response = Http::withToken($this->driverToken)
            ->post($this->baseUrl . "/driver/trips/{$trip->id}/start", [
                'lat' => 31.9539,
                'lng' => 35.9106,
            ]);

        if (!$response->successful()) {
            $this->error('  ✗ Start trip failed: ' . $response->body());
            throw new \RuntimeException('Start trip failed');
        }

        $data = $response->json('data');
        $this->info("  ✓ Trip started at: {$data['started_at']}");
        $this->info("  ✓ Status: {$data['status']}");

        $this->newLine();
    }

    private function step5_completeDestinations(Trip $trip): void
    {
        $this->info('STEP 5: Driver Completes Destinations (Triggers Callbacks)');
        $this->line('─────────────────────────────────────────');

        $trip->load('deliveryRequest.destinations.items');
        $destinations = $trip->deliveryRequest->destinations;

        foreach ($destinations as $index => $destination) {
            $this->line("  Processing destination " . ($index + 1) . ": {$destination->external_id}");

            // Arrive
            $response = Http::withToken($this->driverToken)
                ->post($this->baseUrl . "/driver/trips/{$trip->id}/destinations/{$destination->id}/arrive", [
                    'lat' => $destination->lat,
                    'lng' => $destination->lng,
                ]);

            if (!$response->successful()) {
                $this->warn("    ⚠ Arrive failed: " . $response->body());
            } else {
                $this->info("    ✓ Arrived");
            }

            // Prepare item-level completion data
            // First destination: Full delivery
            // Second destination: Partial delivery (demonstrating discrepancy reporting)
            $items = [];
            if ($destination->items->isNotEmpty()) {
                $this->info("    Items for this destination:");
                foreach ($destination->items as $itemIndex => $item) {
                    $this->line("      - {$item->name} (SKU: {$item->order_item_id}): {$item->quantity_ordered} ordered");

                    if ($index === 0) {
                        // First destination: deliver all items fully
                        $items[] = [
                            'order_item_id' => $item->order_item_id,
                            'quantity_delivered' => $item->quantity_ordered,
                        ];
                    } else {
                        // Second destination: partial delivery for first item
                        if ($itemIndex === 0) {
                            $items[] = [
                                'order_item_id' => $item->order_item_id,
                                'quantity_delivered' => max(0, $item->quantity_ordered - 5),
                                'reason' => 'damaged_in_transit',
                                'notes' => '5 boxes damaged during transport',
                            ];
                        } else {
                            $items[] = [
                                'order_item_id' => $item->order_item_id,
                                'quantity_delivered' => $item->quantity_ordered,
                            ];
                        }
                    }
                }
            }

            // Complete with delivery details and items
            $completePayload = [
                'recipient_name' => $index === 0 ? 'Abu Ahmad' : 'Um Omar',
                'notes' => $index === 0 ? 'Delivered successfully' : 'Partial delivery - some items damaged',
                'lat' => $destination->lat,
                'lng' => $destination->lng,
            ];

            if (!empty($items)) {
                $completePayload['items'] = $items;
                $this->line("    Submitting " . count($items) . " item(s) with delivery data");
            }

            $response = Http::withToken($this->driverToken)
                ->post($this->baseUrl . "/driver/trips/{$trip->id}/destinations/{$destination->id}/complete", $completePayload);

            if (!$response->successful()) {
                $this->error("    ✗ Complete failed: " . $response->body());
            } else {
                $completionType = $index === 0 ? 'FULL DELIVERY' : 'PARTIAL DELIVERY';
                $this->info("    ✓ Completed ({$completionType}) - CALLBACK SHOULD BE SENT!");
            }

            $this->newLine();
        }
    }

    private function step6_summary(Trip $trip): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('                    TEST COMPLETE');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $trip->refresh();
        $trip->load('deliveryRequest.destinations.items');

        $this->info('Summary:');
        $this->line("  Trip ID: {$trip->id}");
        $this->line("  Trip Status: {$trip->status->value}");
        $this->line("  Destinations completed: " . $trip->deliveryRequest->destinations->where('status', 'completed')->count());
        $this->newLine();

        // Show item-level summary
        $this->info('Item Delivery Summary:');
        foreach ($trip->deliveryRequest->destinations as $index => $dest) {
            $this->line("  Destination " . ($index + 1) . ": {$dest->external_id}");
            foreach ($dest->items as $item) {
                $status = $item->quantity_delivered === $item->quantity_ordered
                    ? '✓ Full'
                    : "⚠ Partial ({$item->quantity_delivered}/{$item->quantity_ordered})";
                $this->line("    - {$item->order_item_id}: {$status}");
                if ($item->delivery_reason) {
                    $this->line("      Reason: {$item->delivery_reason->value}");
                }
            }
        }
        $this->newLine();

        $this->info('Check the webhook receiver terminal for callbacks!');
        $this->info('Callbacks should contain (using Melo field names):');
        $this->line('  - order_id (external_id)');
        $this->line('  - delivery_status');
        $this->line('  - delivered_at');
        $this->line('  - received_by');
        $this->line('  - delivered_items[] (with sku, qty_received, discrepancy_reason)');

        $this->newLine();
    }

    private function cleanup(DeliveryRequest $deliveryRequest, Trip $trip): void
    {
        $this->warn('Cleaning up test data...');

        DB::transaction(function () use ($deliveryRequest, $trip) {
            $trip->delete();
            $deliveryRequest->destinations()->delete();
            $deliveryRequest->delete();
        });

        $this->info('  ✓ Test data cleaned up');
    }
}
