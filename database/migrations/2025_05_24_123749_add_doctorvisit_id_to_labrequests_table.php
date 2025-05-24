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
        Schema::table('labrequests', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_visit_id')->nullable();
            $table->foreign('doctor_visit_id')
                  ->references('id')
                  ->on('doctorvisits')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->dropForeign(['doctor_visit_id']);
            $table->dropColumn('doctor_visit_id');
        });
    }
};
