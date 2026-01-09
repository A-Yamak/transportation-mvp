<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id');
            $table->uuid('business_id');
            $table->date('reconciliation_date');

            $table->decimal('total_expected', 10, 2);
            $table->decimal('total_collected', 10, 2);
            $table->decimal('total_cash', 10, 2);
            $table->decimal('total_cliq', 10, 2);

            $table->integer('trips_completed');
            $table->integer('deliveries_completed');
            $table->decimal('total_km_driven', 10, 2);

            $table->enum('status', ['pending', 'submitted', 'acknowledged', 'disputed']);
            $table->json('shop_breakdown'); // Array of {shop_id, amount_collected, method}

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');

            $table->unique(['driver_id', 'reconciliation_date']);
            $table->index('reconciliation_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reconciliations');
    }
};
