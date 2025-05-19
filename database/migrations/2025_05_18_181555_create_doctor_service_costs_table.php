<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_service_costs', function (Blueprint $table) {
            // $table->id(); // Standard pivot tables often omit ID and use composite primary key

            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');

            $table->unsignedBigInteger('sub_service_cost_id');
            $table->foreign('sub_service_cost_id')->references('id')->on('sub_service_costs')->onDelete('cascade');

            $table->primary(['doctor_id', 'sub_service_cost_id']); // Composite primary key
            // No timestamps in original schema
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_service_costs');
    }
};