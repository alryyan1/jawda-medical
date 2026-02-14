<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Room number must be unique within each ward.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unique(['ward_id', 'room_number'], 'rooms_ward_id_room_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique('rooms_ward_id_room_number_unique');
        });
    }
};
