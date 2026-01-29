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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('doctorvisit_id')->nullable();
            $table->unsignedBigInteger('doctor_schedule_id')->nullable();
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('status', 255)->default('pending');
            $table->foreign('doctor_id', 'appointments_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('doctor_schedule_id', 'appointments_doctor_schedule_id_foreign')
                  ->references('id')
                  ->on('doctor_schedules')
                  ->onDelete('cascade');
            $table->foreign('doctorvisit_id', 'appointments_doctorvisit_id_foreign')
                  ->references('id')
                  ->on('doctorvisits')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
