<?php

namespace Tests\Feature\Integration;

use App\Jobs\SendFcmNotificationJob;
use App\Models\DeliveryRequest;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Tests\TestCase;

class TripAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete trip assignment flow triggers driver notification
     *
     * Scenario:
     * 1. Create delivery request with destinations
     * 2. Assign to driver via API
     * 3. Verify notification created
     * 4. Verify FCM job dispatched
     * 5. Verify notification contains correct trip data
     */
    public function test_trip_assignment_sends_notification_to_driver(): void
    {
        Bus::fake();

        // Setup - Create driver with user that has FCM token
        $driver = Driver::factory()
            ->for(User::factory()->state(['fcm_token' => 'test-fcm-token']))
            ->create();
        $vehicle = Vehicle::factory()->create(['is_active' => true]);

        $deliveryRequest = DeliveryRequest::factory()->create(['status' => 'pending']);

        Destination::factory()->count(5)->create([
            'delivery_request_id' => $deliveryRequest->id,
        ]);

        // Authenticate as admin/dispatcher
        Passport::actingAs(User::factory()->admin()->create());

        // Act: Assign trip
        $response = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Assert: Trip assignment successful
        $response->assertStatus(201);
        $tripId = $response['data']['id'];

        // Assert: Notification created for driver's user
        $this->assertDatabaseHas('notifications', [
            'user_id' => $driver->user->id,
            'type' => 'trip_assigned',
            'status' => 'pending',
        ]);

        // Assert: Correct notification data
        $notification = Notification::where('user_id', $driver->user->id)
            ->where('type', 'trip_assigned')
            ->first();

        $this->assertEquals($tripId, $notification->data['trip_id']);
        $this->assertEquals(5, $notification->data['destinations_count']);
        $this->assertEquals('open_trip', $notification->data['action']);

        // Assert: FCM job dispatched
        Bus::assertDispatched(SendFcmNotificationJob::class);
    }

    /**
     * Test notification is not sent if driver has no FCM token
     */
    public function test_notification_not_dispatched_without_fcm_token(): void
    {
        Bus::fake();

        $driver = Driver::factory()
            ->for(User::factory()->state(['fcm_token' => null]))
            ->create();
        $vehicle = Vehicle::factory()->create(['is_active' => true]);
        $deliveryRequest = DeliveryRequest::factory()->create();
        Destination::factory()->count(3)->create(['delivery_request_id' => $deliveryRequest->id]);

        Passport::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Notification still created (pending status)
        $this->assertDatabaseHas('notifications', [
            'user_id' => $driver->user->id,
            'status' => 'pending',
        ]);

        // But FCM job not dispatched
        Bus::assertNotDispatched(SendFcmNotificationJob::class);
    }

    /**
     * Test notification contains correct trip information
     */
    public function test_notification_contains_correct_trip_data(): void
    {
        Bus::fake();

        $driver = Driver::factory()
            ->for(User::factory()->state(['fcm_token' => 'token']))
            ->create();
        $vehicle = Vehicle::factory()->create();

        $deliveryRequest = DeliveryRequest::factory()->create([
            'total_km' => 42.5,
            'estimated_cost' => 85.00,
        ]);

        Destination::factory()->count(8)->create(['delivery_request_id' => $deliveryRequest->id]);

        Passport::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $notification = Notification::where('user_id', $driver->user->id)->first();

        $this->assertEquals(8, $notification->data['destinations_count']);
        $this->assertEquals(42.5, $notification->data['total_km']);
        $this->assertEquals(85.00, $notification->data['estimated_cost']);
    }

    /**
     * Test notification title and body are user-friendly
     */
    public function test_notification_has_clear_title_and_body(): void
    {
        Bus::fake();

        $driver = Driver::factory()
            ->for(User::factory()->state(['fcm_token' => 'token']))
            ->create();
        $vehicle = Vehicle::factory()->create();
        $deliveryRequest = DeliveryRequest::factory()->create();
        Destination::factory()->count(3)->create(['delivery_request_id' => $deliveryRequest->id]);

        Passport::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $notification = Notification::where('user_id', $driver->user->id)->first();

        $this->assertEquals('New Trip Assigned', $notification->title);
        $this->assertStringContainsString('3 deliveries', $notification->body);
    }

    /**
     * Test multiple trips assigned to different drivers each get their notification
     */
    public function test_multiple_trip_assignments_create_separate_notifications(): void
    {
        Bus::fake();

        $driver1 = Driver::factory()
            ->for(User::factory()->state(['fcm_token' => 'token1']))
            ->create();
        $driver2 = Driver::factory()
            ->for(User::factory()->state(['fcm_token' => 'token2']))
            ->create();
        $vehicle = Vehicle::factory()->create();

        $deliveryRequest1 = DeliveryRequest::factory()->create();
        $deliveryRequest2 = DeliveryRequest::factory()->create();

        Destination::factory()->count(3)->create(['delivery_request_id' => $deliveryRequest1->id]);
        Destination::factory()->count(4)->create(['delivery_request_id' => $deliveryRequest2->id]);

        Passport::actingAs(User::factory()->admin()->create());

        $response1 = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest1->id,
            'driver_id' => $driver1->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $response2 = $this->postJson('/api/v1/trips/assign', [
            'delivery_request_id' => $deliveryRequest2->id,
            'driver_id' => $driver2->id,
            'vehicle_id' => $vehicle->id,
        ]);

        // Both trips assigned successfully
        $this->assertEquals(201, $response1->status());
        $this->assertEquals(201, $response2->status());

        // Each driver gets their notification
        $driver1Notifications = Notification::where('user_id', $driver1->user->id)->count();
        $driver2Notifications = Notification::where('user_id', $driver2->user->id)->count();

        $this->assertEquals(1, $driver1Notifications);
        $this->assertEquals(1, $driver2Notifications);

        // Notifications contain correct data
        $notif1 = Notification::where('user_id', $driver1->user->id)->first();
        $notif2 = Notification::where('user_id', $driver2->user->id)->first();

        $this->assertEquals(3, $notif1->data['destinations_count']);
        $this->assertEquals(4, $notif2->data['destinations_count']);
    }
}
