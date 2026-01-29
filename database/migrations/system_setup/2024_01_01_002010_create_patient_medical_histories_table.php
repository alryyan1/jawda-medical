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
        Schema::create('patient_medical_histories', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('patient_id');
            $table->text('allergies')->nullable();
            $table->text('drug_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('past_medical_history')->nullable();
            $table->text('past_surgical_history')->nullable();
            $table->string('general_appearance_summary', 255)->nullable();
            $table->text('skin_summary')->nullable();
            $table->text('head_neck_summary')->nullable();
            $table->text('cardiovascular_summary')->nullable();
            $table->text('respiratory_summary')->nullable();
            $table->text('gastrointestinal_summary')->nullable();
            $table->text('genitourinary_summary')->nullable();
            $table->text('neurological_summary')->nullable();
            $table->text('musculoskeletal_summary')->nullable();
            $table->text('endocrine_summary')->nullable();
            $table->text('peripheral_vascular_summary')->nullable();
            $table->text('present_complains_summary')->nullable();
            $table->text('history_of_present_illness_summary')->nullable();
            $table->string('baseline_bp', 255)->nullable();
            $table->decimal('baseline_temp', 5, 2)->nullable();
            $table->decimal('baseline_weight', 6, 2)->nullable();
            $table->decimal('baseline_height', 5, 2)->nullable();
            $table->string('baseline_heart_rate', 255)->nullable();
            $table->string('baseline_spo2', 255)->nullable();
            $table->string('baseline_rbs', 255)->nullable();
            $table->boolean('chronic_juandice')->nullable();
            $table->boolean('chronic_pallor')->nullable();
            $table->boolean('chronic_clubbing')->nullable();
            $table->boolean('chronic_cyanosis')->nullable();
            $table->boolean('chronic_edema_feet')->nullable();
            $table->boolean('chronic_dehydration_tendency')->nullable();
            $table->boolean('chronic_lymphadenopathy')->nullable();
            $table->boolean('chronic_peripheral_pulses_issue')->nullable();
            $table->boolean('chronic_feet_ulcer_history')->nullable();
            $table->text('overall_care_plan_summary')->nullable();
            $table->text('general_prescription_notes_summary')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['patient_id'], 'patient_medical_histories_patient_id_unique');
            $table->foreign('patient_id', 'patient_medical_histories_patient_id_foreign')
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
        Schema::dropIfExists('patient_medical_histories');
    }
};
