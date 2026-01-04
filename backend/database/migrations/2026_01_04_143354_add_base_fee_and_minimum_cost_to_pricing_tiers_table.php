<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->decimal('base_fee', 8, 2)->default(0)->after('price_per_km');
            $table->decimal('minimum_cost', 8, 2)->default(0)->after('base_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_tiers', function (Blueprint $table) {
            $table->dropColumn(['base_fee', 'minimum_cost']);
        });
    }
};
