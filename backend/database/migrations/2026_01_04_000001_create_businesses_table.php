<?php

declare(strict_types=1);

use App\Enums\BusinessType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('business_type')->default(BusinessType::BulkOrder->value);
            $table->string('api_key')->unique();
            $table->string('callback_url')->nullable();
            $table->string('callback_api_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
