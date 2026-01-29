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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_definition_id');
            $table->date('attendance_date');
            $table->enum('status', ["present","absent","late_present","early_leave","on_leave","holiday","off_day","sick_leave"])->default('present');
            $table->dateTime('check_in_time')->nullable();
            $table->dateTime('check_out_time')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['user_id', 'attendance_date', 'shift_definition_id'], 'user_day_shift_unique');
            $table->foreign('recorded_by_user_id', 'attendances_recorded_by_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('shift_definition_id', 'attendances_shift_definition_id_foreign')
                  ->references('id')
                  ->on('shifts_definitions')
                  ->onDelete('cascade');
            $table->foreign('supervisor_id', 'attendances_supervisor_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'attendances_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
