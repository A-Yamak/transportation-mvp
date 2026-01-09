<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->foreignUuid('shop_id')
                ->nullable()
                ->after('delivery_request_id')
                ->constrained('shops')
                ->nullOnDelete();

            $table->index('shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
