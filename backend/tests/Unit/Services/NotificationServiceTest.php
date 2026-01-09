<?php

namespace Tests\Unit\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
    }

    /**
     * Test notifyDriver creates notification and dispatches job
     */
    public function test_notify_driver_creates_notification_and_dispatches_job(): void
    {
        Bus::fake();

        $driver = User::factory()->create(['fcm_token' => 'test-token']);

        $notification = $this->service->notifyDriver(
            $driver,
            'trip_assigned',
            'Trip Assigned',
            'New trip for you',
            ['trip_id' => 'trip-123']
        );

        $this->assertNotNull($notification);
        $this->assertEquals('trip_assigned', $notification->type);
        $this->assertTrue($notification->isPending());

        Bus::assertDispatched(SendFcmNotificationJob::class);
    }

    /**
     * Test notifyDriver does not dispatch job if driver has no FCM token
     */
    public function test_notify_driver_without_fcm_token_does_not_dispatch_job(): void
    {
        Bus::fake();

        $driver = User::factory()->create(['fcm_token' => null]);

        $notification = $this->service->notifyDriver(
            $driver,
            'trip_assigned',
            'Trip Assigned',
            'New trip for you'
        );

        $this->assertNotNull($notification);
        Bus::assertNotDispatched(SendFcmNotificationJob::class);
    }

    /**
     * Test notifyTripAssigned creates correct notification
     */
    public function test_notify_trip_assigned_creates_correct_notification(): void
    {
        Bus::fake();

        $driver = User::factory()->create(['fcm_token' => 'test-token']);
        $tripData = [
            'trip_id' => 'trip-123',
            'destinations_count' => 5,
            'total_km' => 25.5,
            'estimated_cost' => 50.00,
        ];

        $notification = $this->service->notifyTripAssigned($driver, $tripData);

        $this->assertEquals('trip_assigned', $notification->type);
        $this->assertEquals('New Trip Assigned', $notification->title);
        $this->assertStringContainsString('5', $notification->body);
        $this->assertEquals('trip-123', $notification->data['trip_id']);
    }

    /**
     * Test notifyPaymentReceived creates correct notification
     */
    public function test_notify_payment_received_creates_correct_notification(): void
    {
        Bus::fake();

        $driver = User::factory()->create(['fcm_token' => 'test-token']);

        $notification = $this->service->notifyPaymentReceived($driver, 150.75, 'PAY-001');

        $this->assertEquals('payment_received', $notification->type);
        $this->assertStringContainsString('150.75', $notification->body);
        $this->assertEquals(150.75, $notification->data['amount']);
        $this->assertEquals('PAY-001', $notification->data['reference']);
    }

    /**
     * Test notifyMultiple creates notifications for multiple drivers
     */
    public function test_notify_multiple_creates_for_all_drivers(): void
    {
        Bus::fake();

        $drivers = User::factory()->count(3)->create(['fcm_token' => 'test-token']);

        $notifications = $this->service->notifyMultiple(
            $drivers,
            'action_required',
            'Action Required',
            'Please take action'
        );

        $this->assertCount(3, $notifications);
        $this->assertTrue(collect($notifications)->every(fn($n) => $n->type === 'action_required'));
    }

    /**
     * Test retrySendFailed returns count of retried notifications
     */
    public function test_retry_send_failed_returns_count(): void
    {
        Bus::fake();

        $user = User::factory()->create(['fcm_token' => 'test-token']);

        // Create failed notifications
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'created_at' => now()->subHours(1),
        ]);

        // Create old failed notification (should not retry)
        Notification::factory()->create([
            'user_id' => $user->id,
            'status' => 'failed',
            'created_at' => now()->subDays(2),
        ]);

        $count = $this->service->retrySendFailed();

        $this->assertEquals(3, $count);
        Bus::assertDispatchedTimes(SendFcmNotificationJob::class, 3);
    }

    /**
     * Test notification contains correct trip data
     */
    public function test_notification_data_contains_all_trip_information(): void
    {
        Bus::fake();

        $driver = User::factory()->create(['fcm_token' => 'test-token']);
        $tripData = [
            'trip_id' => 'trip-456',
            'destinations_count' => 8,
            'total_km' => 42.0,
            'estimated_cost' => 89.50,
        ];

        $notification = $this->service->notifyTripAssigned($driver, $tripData);

        $this->assertEquals('trip-456', $notification->data['trip_id']);
        $this->assertEquals('open_trip', $notification->data['action']);
        $this->assertEquals(42.0, $notification->data['total_km']);
        $this->assertEquals(89.50, $notification->data['estimated_cost']);
    }

    /**
     * Test notifyActionRequired with custom data
     */
    public function test_notify_action_required_with_custom_data(): void
    {
        Bus::fake();

        $driver = User::factory()->create(['fcm_token' => 'test-token']);
        $customData = [
            'action_id' => 'action-123',
            'priority' => 'high',
            'deadline' => now()->addHours(2)->toIso8601String(),
        ];

        $notification = $this->service->notifyActionRequired(
            $driver,
            'Urgent Action',
            'Please complete this urgently',
            $customData
        );

        $this->assertEquals('action_required', $notification->type);
        $this->assertEquals('action-123', $notification->data['action_id']);
        $this->assertEquals('high', $notification->data['priority']);
    }
}
