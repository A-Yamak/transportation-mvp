<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add profile_photo_path to drivers table.
 *
 * Stores the path to driver's profile photo in cloud storage (R2/S3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('profile_photo_path')
                ->nullable()
                ->after('license_number')
                ->comment('Path to profile photo in cloud storage');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }
};
