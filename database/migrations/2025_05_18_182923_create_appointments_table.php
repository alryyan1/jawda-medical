<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('doctor_id'); // Doctor for the appointment
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');

            $table->unsignedBigInteger('doctorvisit_id')->unique()->nullable(); // Link to the doctor visit record, unique if one appointment per visit. Made nullable if appointment can exist before visit.
            // Original schema has NOT NULL for doctorvisit_id. If an appointment always creates a visit record immediately, then NOT NULL is fine.
            // If an appointment can exist without an immediate visit record, it should be nullable.
            // $table->foreign('doctorvisit_id')->references('id')->on('doctorvisits')->onDelete('cascade'); // Or restrict/set null

            $table->unsignedBigInteger('doctor_schedule_id')->nullable(); // Optional link to schedule slot
            $table->foreign('doctor_schedule_id')->references('id')->on('doctor_schedules')->onDelete('set null');

            // Consider storing patient_id directly for easier querying of appointments by patient,
            // even if it can be derived through doctorvisit_id.
            // $table->unsignedBigInteger('patient_id');
            // $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');

            $table->date('appointment_date');
            $table->time('appointment_time');
            
            $statusEnum = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show', 'rescheduled'];
            $table->enum('status', $statusEnum)->default('pending');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Optional: Prevent double booking for the same doctor at the same date/time
            // $table->unique(['doctor_id', 'appointment_date', 'appointment_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};