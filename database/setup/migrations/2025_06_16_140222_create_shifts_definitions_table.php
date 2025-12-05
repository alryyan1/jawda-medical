<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Morning Shift"
            $table->string('shift_label')->unique(); // e.g., "Shift 1", "Shift 2"
            $table->time('start_time');
            $table->time('end_time');
            // $table->decimal('duration_hours', 4, 2)->storedAs('TIMESTAMPDIFF(MINUTE, start_time, end_time) / 60'); // For MySQL if needed, or calculate in model
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts_definitions');
    }
};