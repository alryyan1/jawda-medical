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
            $table->id('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_visit_id');
            $table->unsignedBigInteger('audited_by_user_id')->nullable();
            $table->timestamp('audited_at')->nullable();
            $table->enum('status', ["pending_review","verified","needs_correction","rejected"])->default('pending_review');
            $table->text('auditor_notes')->nullable();
            $table->longText('original_patient_data_snapshot')->nullable();
            $table->string('edited_patient_name', 255)->nullable();
            $table->string('edited_phone', 25)->nullable();
            $table->enum('edited_gender', ["male","female","other"])->nullable();
            $table->integer('edited_age_year')->nullable();
            $table->integer('edited_age_month')->nullable();
            $table->integer('edited_age_day')->nullable();
            $table->text('edited_address')->nullable();
            $table->unsignedBigInteger('edited_doctor_id')->nullable();
            $table->string('edited_insurance_no', 255)->nullable();
            $table->date('edited_expire_date')->nullable();
            $table->string('edited_guarantor', 255)->nullable();
            $table->unsignedBigInteger('edited_subcompany_id')->nullable();
            $table->unsignedBigInteger('edited_company_relation_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('audited_by_user_id', 'audited_patient_records_audited_by_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('doctor_visit_id', 'audited_patient_records_doctor_visit_id_foreign')
                  ->references('id')
                  ->on('doctorvisits')
                  ->onDelete('cascade');
            $table->foreign('edited_company_relation_id', 'audited_patient_records_edited_company_relation_id_foreign')
                  ->references('id')
                  ->on('company_relations')
                  ->onDelete('cascade');
            $table->foreign('edited_doctor_id', 'audited_patient_records_edited_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('edited_subcompany_id', 'audited_patient_records_edited_subcompany_id_foreign')
                  ->references('id')
                  ->on('subcompanies')
                  ->onDelete('cascade');
            $table->foreign('patient_id', 'audited_patient_records_patient_id_foreign')
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
        Schema::dropIfExists('audited_patient_records');
    }
};
