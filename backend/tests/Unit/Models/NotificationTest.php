<?php

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Tests\TestCase as BaseTestCase;

class NotificationTest extends BaseTestCase
{
    /**
     * Test notification creation with required fields
     */
    public function test_notification_can_be_created(): void
    {
        $user = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'trip_assigned',
            'title' => 'Trip Assigned',
            'body' => 'New trip assigned',
            'data' => ['trip_id' => 'trip-123'],
            'status' => 'pending',
        ]);

        $this->assertNotNull($notification->id);
        $this->assertEquals($user->id, $notification->user_id);
        $this->assertEquals('trip_assigned', $notification->type);
    }

    /**
     * Test notification marks as read
     */
    public function test_notification_can_be_marked_as_read(): void
    {
        $notification = Notification::factory()->create(['read_at' => null]);

        $this->assertTrue($notification->isUnread());
        $this->assertFalse($notification->isRead());

        $notification->markAsRead();

        $this->assertNotNull($notification->read_at);
        $this->assertTrue($notification->isRead());
        $this->assertFalse($notification->isUnread());
    }

    /**
     * Test notification marks as unread
     */
    public function test_notification_can_be_marked_as_unread(): void
    {
        $notification = Notification::factory()->create(['read_at' => now()]);

        $this->assertTrue($notification->isRead());

        $notification->markAsUnread();

        $this->assertNull($notification->read_at);
        $this->assertTrue($notification->isUnread());
    }

    /**
     * Test notification marks as sent
     */
    public function test_notification_can_be_marked_as_sent(): void
    {
        $notification = Notification::factory()->create(['status' => 'pending', 'sent_at' => null]);

        $this->assertTrue($notification->isPending());
        $this->assertFalse($notification->isSent());

        $notification->markAsSent();

        $this->assertTrue($notification->isSent());
        $this->assertNotNull($notification->sent_at);
    }

    /**
     * Test notification marks as failed
     */
    public function test_notification_can_be_marked_as_failed(): void
    {
        $notification = Notification::factory()->create(['status' => 'pending']);

        $this->assertTrue($notification->isPending());

        $notification->markAsFailed();

        $this->assertTrue($notification->isFailed());
        $this->assertEquals('failed', $notification->status);
    }

    /**
     * Test unread scope returns only unread notifications
     */
    public function test_unread_scope_filters_correctly(): void
    {
        $user = User::factory()->create();

        // Create mix of read and unread
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $unreadCount = Notification::forDriver($user)->unread()->count();

        $this->assertEquals(3, $unreadCount);
    }

    /**
     * Test sent scope returns only sent notifications
     */
    public function test_sent_scope_filters_correctly(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'sent',
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $sentCount = Notification::forDriver($user)->sent()->count();

        $this->assertEquals(2, $sentCount);
    }

    /**
     * Test ofType scope filters by notification type
     */
    public function test_of_type_scope_filters_correctly(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(2)->tripAssigned($user)->create();
        Notification::factory()->count(1)->paymentReceived($user)->create();

        $tripCount = Notification::forDriver($user)->ofType('trip_assigned')->count();

        $this->assertEquals(2, $tripCount);
    }

    /**
     * Test forDriver scope filters by user
     */
    public function test_for_driver_scope_filters_correctly(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Notification::factory()->count(3)->create(['user_id' => $user1->id]);
        Notification::factory()->count(2)->create(['user_id' => $user2->id]);

        $user1Notifications = Notification::forDriver($user1)->count();

        $this->assertEquals(3, $user1Notifications);
    }

    /**
     * Test notification data is stored as JSON
     */
    public function test_notification_data_is_json_serialized(): void
    {
        $data = [
            'trip_id' => 'trip-123',
            'destinations_count' => 5,
            'total_km' => 25.5,
        ];

        $notification = Notification::factory()->create(['data' => $data]);

        $this->assertIsArray($notification->data);
        $this->assertEquals('trip-123', $notification->data['trip_id']);
        $this->assertEquals(5, $notification->data['destinations_count']);
    }
}
