<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('business_type')->nullable(); // null = default for all
            $table->decimal('price_per_km', 8, 4);
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'effective_date']);
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_tiers');
    }
};
