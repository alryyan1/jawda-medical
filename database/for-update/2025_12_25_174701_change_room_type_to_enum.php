<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change room_type column to enum
        DB::statement("ALTER TABLE rooms MODIFY COLUMN room_type ENUM('normal', 'vip') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to string
        DB::statement("ALTER TABLE rooms MODIFY COLUMN room_type VARCHAR(255) NULL");
    }
};
