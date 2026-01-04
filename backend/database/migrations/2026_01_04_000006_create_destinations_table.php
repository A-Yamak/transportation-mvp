<?php

declare(strict_types=1);

use App\Enums\DestinationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_request_id')->constrained()->cascadeOnDelete();
            $table->string('external_id'); // From client ERP
            $table->string('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('sequence_order');
            $table->string('status')->default(DestinationStatus::Pending->value);
            $table->text('notes')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('failure_reason')->nullable();
            $table->text('failure_notes')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sequence_order');
            $table->index(['delivery_request_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};
