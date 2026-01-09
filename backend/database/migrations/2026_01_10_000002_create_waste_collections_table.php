<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignUuid('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignUuid('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->date('collection_date');
            $table->integer('total_items_count')->default(0);
            $table->timestamp('collected_at')->nullable();
            $table->text('driver_notes')->nullable();
            $table->timestamps();

            $table->index('shop_id');
            $table->index('trip_id');
            $table->index('collection_date');
            $table->index(['business_id', 'collection_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_collections');
    }
};
