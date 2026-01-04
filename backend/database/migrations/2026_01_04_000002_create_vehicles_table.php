<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('license_plate')->unique();
            $table->decimal('total_km_driven', 10, 2)->default(0);
            $table->decimal('monthly_km_app', 10, 2)->default(0);
            $table->date('acquisition_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
