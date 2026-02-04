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
            $table->unsignedBigInteger('short_stay_bed_id')->nullable()->after('bed_id');
            $table->enum('short_stay_duration', ['12h', '24h'])->nullable()->after('short_stay_bed_id');
            
            $table->foreign('short_stay_bed_id')
                  ->references('id')
                  ->on('short_stay_beds')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropForeign(['short_stay_bed_id']);
            $table->dropColumn(['short_stay_bed_id', 'short_stay_duration']);
        });
    }
};
