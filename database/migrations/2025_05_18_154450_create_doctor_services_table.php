<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_services', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');

            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->decimal('percentage', 8, 2);
            $table->decimal('fixed', 8, 2);

            // No timestamps in original schema
            // $table->unique(['doctor_id', 'service_id']); // Optional: if a doctor-service pair should be unique
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_services');
    }
};