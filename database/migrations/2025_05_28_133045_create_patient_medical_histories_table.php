<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_medical_histories', function (Blueprint $table) {
            $table->id();
            // One-to-one relationship with patients table
            $table->foreignId('patient_id')->unique()->constrained('patients')->onDelete('cascade');

            // Key historical information
            $table->text('allergies')->nullable();
            $table->text('drug_history')->nullable()->comment('Chronic medications, past significant drug use');
            $table->text('family_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('past_medical_history')->nullable(); // Replaces 'patient_medical_history'
            $table->text('past_surgical_history')->nullable(); // New, often useful

            // Baseline or summary physical exam findings (if not per-visit)
            // These are often better tracked per visit in a separate 'examinations' table or within 'clinical_notes'
            // If these are truly general, long-standing findings, they can stay here.
            $table->string('general_appearance_summary')->nullable(); // Replaces 'general'
            $table->text('skin_summary')->nullable();           // Replaces 'skin'
            $table->text('head_neck_summary')->nullable();      // Combines 'head', 'neck', 'throat', 'mouth', 'nose', 'ear', 'eyes'
            $table->text('cardiovascular_summary')->nullable(); // Replaces 'cardio_system'
            $table->text('respiratory_summary')->nullable();    // Replaces 'respiratory_system'
            $table->text('gastrointestinal_summary')->nullable(); // Replaces 'git_system'
            $table->text('genitourinary_summary')->nullable();   // Replaces 'genitourinary_system'
            $table->text('neurological_summary')->nullable();    // Replaces 'nervous_system', 'neuropsychiatric_system'
            $table->text('musculoskeletal_summary')->nullable();// Replaces 'musculoskeletal_system'
            $table->text('endocrine_summary')->nullable();      // Replaces 'endocrine_system'
            $table->text('peripheral_vascular_summary')->nullable(); // Replaces 'peripheral_vascular_system'

            // Consider if these truly belong to a general patient history or a specific visit/encounter
            // For now, keeping them here as per your request to move medical info
            $table->text('present_complains_summary')->nullable();    // Summary of chronic or recurring complains
            $table->text('history_of_present_illness_summary')->nullable(); // Summary for chronic conditions
            $table->string('baseline_bp')->nullable();              // Baseline Blood Pressure
            $table->decimal('baseline_temp', 5, 2)->nullable();     // Baseline Temperature
            $table->decimal('baseline_weight', 6, 2)->nullable();   // Baseline Weight
            $table->decimal('baseline_height', 5, 2)->nullable();   // Baseline Height
            $table->string('baseline_heart_rate')->nullable();
            $table->string('baseline_spo2')->nullable();
            $table->string('baseline_rbs')->nullable();             // Baseline Random Blood Sugar

            // Other significant findings from original patients table
            $table->boolean('chronic_juandice')->nullable();    // Renamed for clarity
            $table->boolean('chronic_pallor')->nullable();
            $table->boolean('chronic_clubbing')->nullable();
            $table->boolean('chronic_cyanosis')->nullable();
            $table->boolean('chronic_edema_feet')->nullable();
            $table->boolean('chronic_dehydration_tendency')->nullable();
            $table->boolean('chronic_lymphadenopathy')->nullable();
            $table->boolean('chronic_peripheral_pulses_issue')->nullable();
            $table->boolean('chronic_feet_ulcer_history')->nullable();

            $table->text('overall_care_plan_summary')->nullable(); // Replaces 'care_plan'
            $table->text('general_prescription_notes_summary')->nullable(); // Replaces 'prescription_notes'


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_medical_histories');
    }
};