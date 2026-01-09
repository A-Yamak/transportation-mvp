<?php

namespace Tests\Feature\Api\V1;

use App\Models\Notification;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = User::factory()->create();
        Passport::actingAs($this->driver);
    }

    /**
     * Test driver can register FCM token
     */
    public function test_driver_can_register_fcm_token(): void
    {
        $response = $this->postJson('/api/v1/driver/notifications/register-token', [
            'fcm_token' => 'eJlwO1V-QF6:APA91bHs...',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['fcm_token', 'updated_at']])
            ->assertJsonPath('message', 'FCM token registered successfully');

        $this->driver->refresh();
        $this->assertEquals('eJlwO1V-QF6:APA91bHs...', $this->driver->fcm_token);
        $this->assertNotNull($this->driver->fcm_token_updated_at);
    }

    /**
     * Test FCM token registration requires token
     */
    public function test_register_fcm_token_requires_token(): void
    {
        $response = $this->postJson('/api/v1/driver/notifications/register-token', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('fcm_token');
    }

    /**
     * Test driver can get notifications
     */
    public function test_driver_can_get_notifications(): void
    {
        Notification::factory()->count(5)->create(['user_id' => $this->driver->id]);

        $response = $this->getJson('/api/v1/driver/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'body', 'status', 'read_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertCount(5, $response['data']);
    }

    /**
     * Test driver gets only their notifications
     */
    public function test_driver_gets_only_their_notifications(): void
    {
        $otherDriver = User::factory()->create();

        Notification::factory()->count(3)->create(['user_id' => $this->driver->id]);
        Notification::factory()->count(2)->create(['user_id' => $otherDriver->id]);

        $response = $this->getJson('/api/v1/driver/notifications');

        $this->assertCount(3, $response['data']);
    }

    /**
     * Test driver can get unread count
     */
    public function test_driver_can_get_unread_count(): void
    {
        Notification::factory()->count(3)->unread()->create(['user_id' => $this->driver->id]);
        Notification::factory()->count(2)->read()->create(['user_id' => $this->driver->id]);

        $response = $this->getJson('/api/v1/driver/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('data.unread_count', 3);
    }

    /**
     * Test driver can get unread notifications
     */
    public function test_driver_can_get_unread_notifications(): void
    {
        Notification::factory()->count(2)->unread()->create(['user_id' => $this->driver->id]);
        Notification::factory()->count(3)->read()->create(['user_id' => $this->driver->id]);

        $response = $this->getJson('/api/v1/driver/notifications/unread');

        $response->assertStatus(200);
        $this->assertCount(2, $response['data']);
    }

    /**
     * Test driver can mark notification as read
     */
    public function test_driver_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->unread()->create(['user_id' => $this->driver->id]);

        $response = $this->patchJson(
            "/api/v1/driver/notifications/{$notification->id}/read"
        );

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Notification marked as read');

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test driver cannot mark other driver's notification as read
     */
    public function test_driver_cannot_mark_others_notification_as_read(): void
    {
        $otherDriver = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $otherDriver->id]);

        $response = $this->patchJson(
            "/api/v1/driver/notifications/{$notification->id}/read"
        );

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized');
    }

    /**
     * Test driver can mark notification as unread
     */
    public function test_driver_can_mark_notification_as_unread(): void
    {
        $notification = Notification::factory()->read()->create(['user_id' => $this->driver->id]);

        $response = $this->patchJson(
            "/api/v1/driver/notifications/{$notification->id}/unread"
        );

        $response->assertStatus(200);

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }

    /**
     * Test driver can mark all notifications as read
     */
    public function test_driver_can_mark_all_as_read(): void
    {
        Notification::factory()->count(5)->unread()->create(['user_id' => $this->driver->id]);

        $response = $this->patchJson('/api/v1/driver/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'All notifications marked as read');

        $unreadCount = Notification::forDriver($this->driver)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test driver can delete notification
     */
    public function test_driver_can_delete_notification(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->driver->id]);

        $response = $this->deleteJson(
            "/api/v1/driver/notifications/{$notification->id}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Notification deleted');

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    /**
     * Test driver cannot delete other driver's notification
     */
    public function test_driver_cannot_delete_others_notification(): void
    {
        $otherDriver = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $otherDriver->id]);

        $response = $this->deleteJson(
            "/api/v1/driver/notifications/{$notification->id}"
        );

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    /**
     * Test unauthenticated user cannot access notifications
     */
    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        Passport::actingAs(null);

        $response = $this->getJson('/api/v1/driver/notifications');

        $response->assertStatus(401);
    }
}
