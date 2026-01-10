<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('waste_collection_items')) {
            return;
        }

        Schema::create('waste_collection_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('waste_collection_id')->constrained('waste_collections')->cascadeOnDelete();
            $table->foreignUuid('destination_item_id')->nullable()->constrained('destination_items')->nullOnDelete();
            $table->string('order_item_id')->index();
            $table->string('product_name')->nullable();
            $table->integer('quantity_delivered');
            $table->date('delivered_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->integer('pieces_waste')->default(0);
            // Generated column: pieces_sold = quantity_delivered - pieces_waste
            $table->integer('pieces_sold')->storedAs('quantity_delivered - pieces_waste');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('waste_collection_id');
            // order_item_id already indexed via ->index() on line 19
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_collection_items');
    }
};
