<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_default_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_definition_id')->constrained('shifts_definitions')->onDelete('cascade');
            // Add day_of_week if a user can have different default shifts for different days.
            // $table->tinyInteger('day_of_week')->nullable(); // 0 (Sun) - 6 (Sat)
            $table->unique(['user_id', 'shift_definition_id']); // Ensures a user is assigned to a shift definition only once as default
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_default_shifts');
    }
};