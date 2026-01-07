<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add fuel efficiency tracking to vehicles and price_per_km to drivers.
 *
 * Vehicle fuel data:
 * - tank_capacity_liters: Full tank capacity (e.g., 51.1 liters)
 * - full_tank_range_km: KM on a full tank (e.g., 671 km)
 * - km_per_liter is calculated: full_tank_range_km / tank_capacity_liters
 *
 * Driver pricing:
 * - price_per_km: What the driver charges per kilometer
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add fuel efficiency fields to vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('tank_capacity_liters', 5, 2)->nullable()->after('monthly_km_app');
            $table->decimal('full_tank_range_km', 6, 2)->nullable()->after('tank_capacity_liters');
        });

        // Add price_per_km to drivers
        Schema::table('drivers', function (Blueprint $table) {
            $table->decimal('price_per_km', 6, 2)->nullable()->after('license_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['tank_capacity_liters', 'full_tank_range_km']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('price_per_km');
        });
    }
};
