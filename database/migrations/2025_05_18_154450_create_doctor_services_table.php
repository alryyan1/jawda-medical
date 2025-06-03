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
            $table->foreignId('doctor_id')->constrained('doctors')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->decimal('percentage', 5, 2)->nullable(); // e.g., 20.00 for 20%
            $table->decimal('fixed', 15, 2)->nullable(); // Fixed amount the doctor receives

            // Ensure a doctor can only have one entry per service
            $table->unique(['doctor_id', 'service_id']);
            // No timestamps in original schema
            // $table->unique(['doctor_id', 'service_id']); // Optional: if a doctor-service pair should be unique
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_services');
    }
};