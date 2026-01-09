<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('start_odometer_km', 10, 2)->nullable()
                ->after('started_at');
            $table->decimal('end_odometer_km', 10, 2)->nullable()
                ->after('completed_at');
            $table->decimal('actual_km_driven', 10, 2)->nullable()
                ->after('end_odometer_km');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['start_odometer_km', 'end_odometer_km', 'actual_km_driven']);
        });
    }
};
