<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->enum('payment_method', ['cash', 'cliq_now', 'cliq_later', 'mixed', 'pending'])
                ->default('pending')
                ->after('amount_collected');
            $table->enum('payment_status', ['pending', 'collected', 'partial', 'failed'])
                ->default('pending')
                ->after('payment_method');
            $table->string('payment_reference')->nullable()
                ->after('payment_status');
            $table->timestamp('payment_collected_at')->nullable()
                ->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status', 'payment_reference', 'payment_collected_at']);
        });
    }
};
