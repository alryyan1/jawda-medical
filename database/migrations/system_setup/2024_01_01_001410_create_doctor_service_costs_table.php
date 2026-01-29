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
        Schema::create('doctor_service_costs', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('sub_service_cost_id');
            $table->foreign('doctor_id', 'doctor_service_costs_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('sub_service_cost_id', 'doctor_service_costs_sub_service_cost_id_foreign')
                  ->references('id')
                  ->on('sub_service_costs')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_service_costs');
    }
};
