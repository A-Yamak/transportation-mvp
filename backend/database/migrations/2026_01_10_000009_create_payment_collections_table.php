<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_collections')) {
            return;
        }
        Schema::create('payment_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('destination_id');
            $table->uuid('trip_id');
            $table->uuid('driver_id');

            $table->decimal('amount_expected', 10, 2);
            $table->decimal('amount_collected', 10, 2);

            $table->enum('payment_method', ['cash', 'cliq_now', 'cliq_later', 'mixed']);
            $table->string('cliq_reference')->nullable(); // CliQ transaction ID
            $table->enum('payment_status', ['pending', 'collected', 'partial', 'failed']);

            $table->string('shortage_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->foreign('destination_id')->references('id')->on('destinations')->onDelete('cascade');
            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');

            $table->index('trip_id');
            $table->index('payment_status');
            $table->index('destination_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_collections');
    }
};
