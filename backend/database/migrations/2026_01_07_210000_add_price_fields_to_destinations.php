<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add price tracking fields to destinations and destination_items.
 *
 * - destination_items.unit_price: Price per unit of the item
 * - destinations.amount_to_collect: Total cash to collect from customer
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add unit_price to destination_items
        Schema::table('destination_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('name');
        });

        // Add amount_to_collect to destinations
        Schema::table('destinations', function (Blueprint $table) {
            $table->decimal('amount_to_collect', 10, 2)->nullable()->after('notes');
            $table->decimal('amount_collected', 10, 2)->nullable()->after('amount_to_collect');
            $table->string('contact_name', 100)->nullable()->after('lng');
            $table->string('contact_phone', 20)->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('destination_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn(['amount_to_collect', 'amount_collected', 'contact_name', 'contact_phone']);
        });
    }
};
