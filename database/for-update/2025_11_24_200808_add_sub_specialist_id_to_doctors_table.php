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
        Schema::table('doctors', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_specialist_id')->nullable()->after('specialist_id');
            
            $table->foreign('sub_specialist_id')
                  ->references('id')
                  ->on('sub_specialists')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropForeign(['sub_specialist_id']);
            $table->dropColumn('sub_specialist_id');
        });
    }
};
