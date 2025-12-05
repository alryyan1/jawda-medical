<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_definition_id')->constrained('shifts_definitions')->onDelete('cascade');
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late_present', 'early_leave', 'on_leave', 'holiday', 'off_day', 'sick_leave'])->default('present');
            $table->dateTime('check_in_time')->nullable();
            $table->dateTime('check_out_time')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->unique(['user_id', 'attendance_date', 'shift_definition_id'], 'user_day_shift_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};