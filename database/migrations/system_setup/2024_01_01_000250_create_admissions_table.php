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
        Schema::create('admissions', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('ward_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('bed_id');
            $table->date('admission_date');
            $table->time('admission_time')->nullable();
            $table->date('discharge_date')->nullable();
            $table->time('discharge_time')->nullable();
            $table->string('admission_type', 255)->nullable();
            $table->text('admission_reason')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('provisional_diagnosis')->nullable();
            $table->text('operations')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('current_medications')->nullable();
            $table->string('referral_source', 255)->nullable();
            $table->date('expected_discharge_date')->nullable();
            $table->string('next_of_kin_name', 255)->nullable();
            $table->string('next_of_kin_relation', 255)->nullable();
            $table->string('next_of_kin_phone', 255)->nullable();
            $table->enum('status', ["admitted","discharged","transferred"])->default('admitted');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('specialist_doctor_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('notes')->nullable();
            $table->decimal('initial_deposit', 10, 2)->default(0.00);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('bed_id', 'admissions_bed_id_foreign')
                  ->references('id')
                  ->on('beds')
                  ->onDelete('cascade');
            $table->foreign('doctor_id', 'admissions_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('patient_id', 'admissions_patient_id_foreign')
                  ->references('id')
                  ->on('patients')
                  ->onDelete('cascade');
            $table->foreign('room_id', 'admissions_room_id_foreign')
                  ->references('id')
                  ->on('rooms')
                  ->onDelete('cascade');
            $table->foreign('specialist_doctor_id', 'admissions_specialist_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'admissions_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('ward_id', 'admissions_ward_id_foreign')
                  ->references('id')
                  ->on('wards')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
