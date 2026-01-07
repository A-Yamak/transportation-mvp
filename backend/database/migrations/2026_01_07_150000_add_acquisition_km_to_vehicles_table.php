<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add acquisition_km to vehicles table.
 *
 * This tracks the odometer reading when the vehicle was acquired.
 * app_tracked_km = total_km_driven - acquisition_km
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('acquisition_km', 10, 2)
                ->default(0)
                ->after('acquisition_date')
                ->comment('Odometer reading when vehicle was acquired');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('acquisition_km');
        });
    }
};
