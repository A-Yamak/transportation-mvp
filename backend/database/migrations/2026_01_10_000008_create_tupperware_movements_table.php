<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tupperware_movements')) {
            return;
        }
        Schema::create('tupperware_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shop_id');
            $table->uuid('destination_id')->nullable();
            $table->uuid('trip_id');
            $table->uuid('driver_id');
            $table->uuid('business_id');

            $table->string('product_type'); // "box", "tray", "bag", etc.
            $table->integer('quantity_delivered')->default(0);
            $table->integer('quantity_returned')->default(0);
            $table->integer('shop_balance_before');
            $table->integer('shop_balance_after');

            $table->enum('movement_type', ['delivery', 'return', 'adjustment']);
            $table->text('notes')->nullable();
            $table->timestamp('movement_at');
            $table->timestamps();

            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->foreign('destination_id')->references('id')->on('destinations')->onDelete('set null');
            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');

            $table->index(['shop_id', 'movement_at']);
            $table->index('trip_id');
            $table->index('driver_id');
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tupperware_movements');
    }
};
