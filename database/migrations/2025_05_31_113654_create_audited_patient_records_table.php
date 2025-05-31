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
        Schema::create('audited_patient_records', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade'); // Link to original patient
            $table->foreignId('doctor_visit_id')->constrained('doctorvisits')->onDelete('cascade')->unique(); // Each visit audited once
            
            $table->foreignId('audited_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('audited_at')->nullable();
            
            $table->enum('status', ['pending_review', 'verified', 'needs_correction', 'rejected'])->default('pending_review');
            $table->text('auditor_notes')->nullable();
            
            $table->json('original_patient_data_snapshot')->nullable(); // Store key original patient fields

            // Fields for audited/edited patient demographic and insurance information
            // These store the state *after* auditor review/edits, if any.
            // The original values can be found via patient_id or the snapshot.
            $table->string('edited_patient_name')->nullable();
            $table->string('edited_phone', 25)->nullable();
            $table->enum('edited_gender', ['male', 'female', 'other'])->nullable();
            $table->integer('edited_age_year')->nullable();
            $table->integer('edited_age_month')->nullable();
            $table->integer('edited_age_day')->nullable();
            $table->text('edited_address')->nullable();
            
            $table->foreignId('edited_doctor_id')->nullable()->constrained('doctors')->onDelete('set null'); // If auditor changes the doctor for the claim
            
            // Audited Insurance Details (these are based on the original patient's company_id)
            // The company_id itself is not changed on this record, it's taken from original patient.
            $table->string('edited_insurance_no')->nullable();
            $table->date('edited_expire_date')->nullable();
            $table->string('edited_guarantor')->nullable();
            $table->foreignId('edited_subcompany_id')->nullable()->constrained('subcompanies')->onDelete('set null');
            $table->foreignId('edited_company_relation_id')->nullable()->constrained('company_relations')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audited_patient_records');
    }
};