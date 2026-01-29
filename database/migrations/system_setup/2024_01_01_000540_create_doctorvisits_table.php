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
        Schema::create('doctorvisits', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('doctor_shift_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('is_new')->default(1);
            $table->integer('number')->default(0);
            $table->integer('only_lab')->default(0);
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('file_id')->nullable();
            $table->date('visit_date');
            $table->time('visit_time')->nullable();
            $table->string('status', 255)->default('waiting');
            $table->string('visit_type', 255)->nullable();
            $table->integer('queue_number')->nullable();
            $table->text('reason_for_visit')->nullable();
            $table->text('visit_notes')->nullable();
            $table->unique(['patient_id', 'doctor_shift_id'], 'doctorvisits_patient_id_doctor_shift_id_unique');
            $table->foreign('doctor_shift_id', 'doctorvisits_doctor_shift_id_foreign')
                  ->references('id')
                  ->on('doctor_shifts')
                  ->onDelete('cascade');
            $table->foreign('patient_id', 'doctorvisits_patient_id_foreign')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctorvisits');
    }
};
