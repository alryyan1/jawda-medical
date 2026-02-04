<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['ward_id']);
            $table->dropForeign(['room_id']);
            $table->dropForeign(['bed_id']);
            
            // Make columns nullable
            $table->unsignedBigInteger('ward_id')->nullable()->change();
            $table->unsignedBigInteger('room_id')->nullable()->change();
            $table->unsignedBigInteger('bed_id')->nullable()->change();
            
            // Re-add foreign key constraints with nullable support
            $table->foreign('ward_id', 'admissions_ward_id_foreign')
                  ->references('id')
                  ->on('wards')
                  ->onDelete('cascade');
            $table->foreign('room_id', 'admissions_room_id_foreign')
                  ->references('id')
                  ->on('rooms')
                  ->onDelete('cascade');
            $table->foreign('bed_id', 'admissions_bed_id_foreign')
                  ->references('id')
                  ->on('beds')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['ward_id']);
            $table->dropForeign(['room_id']);
            $table->dropForeign(['bed_id']);
            
            // Make columns non-nullable again
            $table->unsignedBigInteger('ward_id')->nullable(false)->change();
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
            $table->unsignedBigInteger('bed_id')->nullable(false)->change();
            
            // Re-add foreign key constraints
            $table->foreign('ward_id', 'admissions_ward_id_foreign')
                  ->references('id')
                  ->on('wards')
                  ->onDelete('cascade');
            $table->foreign('room_id', 'admissions_room_id_foreign')
                  ->references('id')
                  ->on('rooms')
                  ->onDelete('cascade');
            $table->foreign('bed_id', 'admissions_bed_id_foreign')
                  ->references('id')
                  ->on('beds')
                  ->onDelete('cascade');
        });
    }
};
