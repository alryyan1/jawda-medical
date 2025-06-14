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
        Schema::table('requested_results', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable();
            if (Schema::hasTable('units')) {
                $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_results', function (Blueprint $table) {
            if (Schema::hasTable('units')) {
                $table->dropForeign(['unit_id']);
            }
            $table->dropColumn('unit_id');
        });
    }
};
