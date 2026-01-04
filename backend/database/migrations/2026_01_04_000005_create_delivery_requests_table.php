<?php

declare(strict_types=1);

use App\Enums\DeliveryRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(DeliveryRequestStatus::Pending->value);
            $table->decimal('total_km', 10, 2)->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_km', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->json('optimized_route')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('requested_at');
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_requests');
    }
};
