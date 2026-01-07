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

        $trip->load('deliveryRequest.destinations');
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

            // Complete with delivery details
            $response = Http::withToken($this->driverToken)
                ->post($this->baseUrl . "/driver/trips/{$trip->id}/destinations/{$destination->id}/complete", [
                    'recipient_name' => 'Test Recipient',
                    'notes' => 'Delivered successfully',
                    'lat' => $destination->lat,
                    'lng' => $destination->lng,
                ]);

            if (!$response->successful()) {
                $this->error("    ✗ Complete failed: " . $response->body());
            } else {
                $this->info("    ✓ Completed - CALLBACK SHOULD BE SENT!");
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
        $trip->load('deliveryRequest.destinations');

        $this->info('Summary:');
        $this->line("  Trip ID: {$trip->id}");
        $this->line("  Trip Status: {$trip->status->value}");
        $this->line("  Destinations completed: " . $trip->deliveryRequest->destinations->where('status', 'completed')->count());
        $this->newLine();

        $this->info('Check the webhook receiver terminal for callbacks!');
        $this->info('Callbacks should contain:');
        $this->line('  - order_id (external_id)');
        $this->line('  - delivery_status');
        $this->line('  - delivered_at');
        $this->line('  - received_by');

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
