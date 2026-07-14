<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_default_shifts');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shifts_definitions');
        Schema::dropIfExists('attendance_settings');
    }

    public function down(): void
    {
        // Attendance feature removed; not reversible.
    }
};
