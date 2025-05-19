<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('doctor_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');

            // Storing day of week (e.g., 0 for Sunday, 1 for Monday ... 6 for Saturday)
            // Or 1 for Monday ... 7 for Sunday (ISO-8601)
            // Ensure consistency with how you plan to use this.
            $table->tinyInteger('day_of_week')->unsigned(); // Values 0-6 or 1-7
            $table->enum('time_slot', ['morning', 'evening', 'afternoon', 'full_day']); // Added more common slots
            // Original schema: $table->enum('time_slot', ['morning', 'evening']);

            // A doctor might have a unique schedule per day and time slot
            $table->unique(['doctor_id', 'day_of_week', 'time_slot']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
    }
};