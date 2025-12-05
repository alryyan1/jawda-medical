<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_audit_and_medical_history_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audited_patient_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('doctor_visit_id')->constrained('doctorvisits')->onDelete('cascade');
            $table->foreignId('audited_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('audited_at')->nullable();
            $table->enum('status', ['pending_review','verified','needs_correction','rejected'])->default('pending_review');
            $table->text('auditor_notes')->nullable();
            $table->json('original_patient_data_snapshot')->nullable();
            $table->string('edited_patient_name')->nullable();
            $table->string('edited_phone', 25)->nullable();
            $table->enum('edited_gender', ['male','female','other'])->nullable();
            $table->integer('edited_age_year')->nullable();
            $table->integer('edited_age_month')->nullable();
            $table->integer('edited_age_day')->nullable();
            $table->text('edited_address')->nullable();
            $table->foreignId('edited_doctor_id')->nullable()->constrained('doctors')->onDelete('set null');
            $table->string('edited_insurance_no')->nullable();
            $table->date('edited_expire_date')->nullable();
            $table->string('edited_guarantor')->nullable();
            $table->foreignId('edited_subcompany_id')->nullable()->constrained('subcompanies')->onDelete('set null');
            $table->foreignId('edited_company_relation_id')->nullable()->constrained('company_relations')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('audited_requested_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audited_patient_record_id')->constrained('audited_patient_records')->onDelete('cascade');
            $table->foreignId('original_requested_service_id')->nullable()->constrained('requested_services')->onDelete('set null');
            $table->foreignId('service_id')->constrained('services');
            $table->decimal('audited_price', 15, 2);
            $table->integer('audited_count')->default(1);
            $table->decimal('audited_discount_per', 5, 2)->default(0.00);
            $table->decimal('audited_discount_fixed', 15, 2)->default(0.00);
            $table->decimal('audited_endurance', 15, 2)->default(0.00);
            $table->enum('audited_status', ['pending_review','approved_for_claim','rejected_by_auditor','needs_edits','cancelled_by_auditor'])->default('pending_review');
            $table->text('auditor_notes_for_service')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_medical_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('patients')->onDelete('cascade');
            $table->text('allergies')->nullable();
            $table->text('drug_history')->nullable()->comment('Chronic medications, past significant drug use');
            $table->text('family_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('past_medical_history')->nullable();
            $table->text('past_surgical_history')->nullable();
            $table->string('general_appearance_summary')->nullable();
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
            $table->string('baseline_bp')->nullable();
            $table->decimal('baseline_temp', 5, 2)->nullable();
            $table->decimal('baseline_weight', 6, 2)->nullable();
            $table->decimal('baseline_height', 5, 2)->nullable();
            $table->string('baseline_heart_rate')->nullable();
            $table->string('baseline_spo2')->nullable();
            $table->string('baseline_rbs')->nullable();
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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audited_requested_services');
        Schema::dropIfExists('audited_patient_records');
        Schema::dropIfExists('patient_medical_histories');
    }
};