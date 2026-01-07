<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('destination_id')->constrained()->cascadeOnDelete();
            $table->string('order_item_id'); // External item ID from client ERP
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_delivered')->default(0);
            $table->string('delivery_reason')->nullable(); // ItemDeliveryReason enum
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('destination_id');
            $table->index('order_item_id');
            $table->unique(['destination_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_items');
    }
};
