<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove room_id from admissions; room is derivable from bed (bed -> room -> ward).
     */
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropForeign('admissions_room_id_foreign');
            $table->dropColumn('room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('ward_id');
            $table->foreign('room_id', 'admissions_room_id_foreign')
                ->references('id')
                ->on('rooms')
                ->onDelete('cascade');
        });
    }
};
