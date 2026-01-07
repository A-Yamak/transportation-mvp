<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BusinessType;
use App\Models\Business;
use App\Models\BusinessPayloadSchema;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * MVP Production Seeder
 *
 * Creates initial data for production deployment:
 * - Melo Group business with API credentials
 * - Test driver with user account
 * - VW Caddy vehicle
 */
class MvpSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================================
        // Create Melo Group Business
        // =========================================================================
        $meloApiKey = 'melo_' . Str::random(32);

        $melo = Business::updateOrCreate(
            ['name' => 'Melo Group'],
            [
                'business_type' => BusinessType::BulkOrder,
                'api_key' => $meloApiKey,
                'callback_url' => 'https://erp.melogroup.jo/api/delivery-callback', // Update with real URL
                'callback_api_key' => 'callback_' . Str::random(32),
                'is_active' => true,
            ]
        );

        $this->command->info("Created Melo Group business with API key: {$meloApiKey}");

        // Create payload schema for Melo
        BusinessPayloadSchema::updateOrCreate(
            ['business_id' => $melo->id],
            [
                'request_schema' => [
                    'external_id' => 'order_id',
                    'address' => 'delivery_address',
                    'lat' => 'coordinates.latitude',
                    'lng' => 'coordinates.longitude',
                    'contact_name' => 'customer_name',
                    'contact_phone' => 'customer_phone',
                    'notes' => 'delivery_notes',
                ],
                'callback_schema' => [
                    'external_id' => 'order_id',
                    'status' => 'delivery_status',
                    'completed_at' => 'delivered_at',
                    'recipient_name' => 'received_by',
                    'notes' => 'driver_notes',
                    'items' => 'delivered_items',
                ],
            ]
        );

        $this->command->info('Created Melo payload schema');

        // =========================================================================
        // Create Vehicle
        // =========================================================================
        $vehicle = Vehicle::updateOrCreate(
            ['license_plate' => 'AMN-1234'],
            [
                'make' => 'Volkswagen',
                'model' => 'Caddy',
                'year' => 2019,
                'acquisition_km' => 45000.00,  // Odometer reading when acquired
                'total_km_driven' => 45000.00, // Current odometer (will increase with trips)
                'monthly_km_app' => 0.00,
                'acquisition_date' => '2019-06-15',
                'is_active' => true,
            ]
        );

        $this->command->info("Created vehicle: VW Caddy ({$vehicle->license_plate})");

        // =========================================================================
        // Create Driver User
        // =========================================================================
        $driverUser = User::updateOrCreate(
            ['email' => 'driver@alsabiqoon.com'],
            [
                'name' => 'Ahmad Driver',
                'password' => Hash::make('driver123'),
                'email_verified_at' => now(),
            ]
        );

        $driver = Driver::updateOrCreate(
            ['user_id' => $driverUser->id],
            [
                'vehicle_id' => $vehicle->id,
                'phone' => '+962791111111',
                'license_number' => 'DL-12345',
                'is_active' => true,
            ]
        );

        $this->command->info("Created driver: {$driverUser->name} (driver@alsabiqoon.com / driver123)");

        // =========================================================================
        // Create Admin User (for Filament)
        // =========================================================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@alsabiqoon.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Created admin: {$admin->name} (admin@alsabiqoon.com / admin123)");

        // =========================================================================
        // Summary
        // =========================================================================
        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('MVP Seeder Complete!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->info('MELO ERP CREDENTIALS:');
        $this->command->info("  X-API-Key: {$meloApiKey}");
        $this->command->info("  Callback URL: {$melo->callback_url}");
        $this->command->newLine();
        $this->command->info('DRIVER LOGIN:');
        $this->command->info('  Email: driver@alsabiqoon.com');
        $this->command->info('  Password: driver123');
        $this->command->newLine();
        $this->command->info('ADMIN LOGIN:');
        $this->command->info('  Email: admin@alsabiqoon.com');
        $this->command->info('  Password: admin123');
        $this->command->newLine();
    }
}
