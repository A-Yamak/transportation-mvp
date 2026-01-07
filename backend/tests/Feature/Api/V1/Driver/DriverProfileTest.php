<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Driver;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Driver Profile API Tests
 *
 * Tests for driver profile endpoints:
 * - GET /api/v1/driver/profile - Get driver profile with vehicle
 * - PUT /api/v1/driver/profile - Update driver profile
 * - POST /api/v1/driver/profile/photo - Upload profile photo
 */
class DriverProfileTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // GET /api/v1/driver/profile
    // =========================================================================

    public function test_driver_can_get_their_profile(): void
    {
        $vehicle = Vehicle::factory()->create([
            'make' => 'Volkswagen',
            'model' => 'Caddy',
            'year' => 2019,
            'license_plate' => 'AMN-1234',
            'acquisition_km' => 45000,
            'total_km_driven' => 52500,
            'monthly_km_app' => 1200,
            'acquisition_date' => '2019-06-15',
        ]);

        $user = User::factory()->create([
            'name' => 'Ahmad Driver',
            'email' => 'ahmad@test.com',
        ]);

        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'phone' => '+962791111111',
            'license_number' => 'DL-12345',
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'license_number',
                    'profile_photo_url',
                    'is_active',
                    'vehicle' => [
                        'id',
                        'make',
                        'model',
                        'year',
                        'full_name',
                        'license_plate',
                        'acquisition_date',
                        'acquisition_km',
                        'total_km_driven',
                        'monthly_km_app',
                        'app_tracked_km',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Ahmad Driver',
                    'email' => 'ahmad@test.com',
                    'phone' => '+962791111111',
                    'license_number' => 'DL-12345',
                    'vehicle' => [
                        'make' => 'Volkswagen',
                        'model' => 'Caddy',
                        'year' => 2019,
                        'full_name' => 'Volkswagen Caddy (2019)',
                        'license_plate' => 'AMN-1234',
                        'acquisition_km' => 45000,
                        'total_km_driven' => 52500,
                        'app_tracked_km' => 7500, // 52500 - 45000
                    ],
                ],
            ]);
    }

    public function test_driver_profile_shows_null_vehicle_when_not_assigned(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'vehicle_id' => null,
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/profile');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'vehicle' => null,
                ],
            ]);
    }

    public function test_non_driver_user_gets_404(): void
    {
        $user = User::factory()->create();
        // No driver record created for this user

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/v1/driver/profile');

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Driver profile not found',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/v1/driver/profile');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // PUT /api/v1/driver/profile
    // =========================================================================

    public function test_driver_can_update_their_phone_number(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'phone' => '+962791111111',
        ]);

        $this->actingAs($user, 'api');

        $response = $this->putJson('/api/v1/driver/profile', [
            'phone' => '+962792222222',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'phone' => '+962792222222',
                ],
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'phone' => '+962792222222',
        ]);
    }

    public function test_driver_can_update_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api');

        $response = $this->putJson('/api/v1/driver/profile', [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'New Name',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_driver_cannot_update_license_number(): void
    {
        $user = User::factory()->create();
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'license_number' => 'DL-12345',
        ]);

        $this->actingAs($user, 'api');

        $response = $this->putJson('/api/v1/driver/profile', [
            'license_number' => 'DL-99999',
        ]);

        // License number should NOT change (admin-only field)
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'license_number' => 'DL-12345',
        ]);
    }

    public function test_profile_update_validates_phone_format(): void
    {
        $user = User::factory()->create();
        Driver::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api');

        $response = $this->putJson('/api/v1/driver/profile', [
            'phone' => 'invalid-phone',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    // =========================================================================
    // POST /api/v1/driver/profile/photo
    // =========================================================================

    public function test_driver_can_upload_profile_photo(): void
    {
        Storage::fake('r2');

        $user = User::factory()->create();
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api');

        $file = UploadedFile::fake()->image('profile.jpg', 400, 400);

        $response = $this->postJson('/api/v1/driver/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'profile_photo_url',
                ],
            ]);

        // Verify file was stored
        Storage::disk('r2')->assertExists('driver-photos/' . $driver->id . '.jpg');
    }

    public function test_profile_photo_must_be_an_image(): void
    {
        $user = User::factory()->create();
        Driver::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api');

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson('/api/v1/driver/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_profile_photo_max_size_is_5mb(): void
    {
        $user = User::factory()->create();
        Driver::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api');

        $file = UploadedFile::fake()->image('large.jpg')->size(6000); // 6MB

        $response = $this->postJson('/api/v1/driver/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }
}
