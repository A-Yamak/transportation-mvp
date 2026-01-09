<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('trip_type')
                ->default('delivery')
                ->after('vehicle_id')
                ->comment('delivery or waste_collection');

            $table->index('trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['trip_type']);
            $table->dropColumn('trip_type');
        });
    }
};
