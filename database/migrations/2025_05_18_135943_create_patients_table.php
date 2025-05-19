<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('restrict'); // Or cascade

            $table->unsignedBigInteger('user_id'); // Assuming this is the creating/responsible user
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');

            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('set null');

            $table->string('phone', 10); // Consider a more flexible length, e.g., 15-20
            $table->string('gender'); // Or $table->enum('gender', ['male', 'female', 'other']);
            $table->integer('age_day')->nullable();
            $table->integer('age_month')->nullable();
            $table->integer('age_year')->nullable();

            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');

            $table->unsignedBigInteger('subcompany_id')->nullable();
            $table->foreign('subcompany_id')->references('id')->on('subcompanies')->onDelete('set null');

            $table->unsignedBigInteger('company_relation_id')->nullable();
            $table->foreign('company_relation_id')->references('id')->on('company_relations')->onDelete('set null');

            $table->integer('paper_fees')->nullable();
            $table->string('guarantor')->nullable();
            $table->date('expire_date')->nullable();
            $table->string('insurance_no')->nullable();
            $table->boolean('is_lab_paid')->default(false);
            $table->integer('lab_paid')->default(0); // Consider decimal if partial payments are possible
            $table->boolean('result_is_locked')->default(false);
            $table->boolean('sample_collected')->default(false);
            $table->time('sample_collect_time')->nullable();
            $table->dateTime('result_print_date')->nullable();
            $table->dateTime('sample_print_date')->nullable();
            $table->integer('visit_number');
            $table->boolean('result_auth'); // No default in schema, assuming required
            $table->dateTime('auth_date'); // No default in schema, assuming required

            $table->text('present_complains');
            $table->text('history_of_present_illness');
            $table->string('procedures');
            $table->string('provisional_diagnosis');
            $table->string('bp');
            $table->decimal('temp', 8, 2); // Changed from double
            $table->decimal('weight', 8, 2); // Changed from double
            $table->decimal('height', 8, 2); // Changed from double
            $table->boolean('juandice')->nullable();
            $table->boolean('pallor')->nullable();
            $table->boolean('clubbing')->nullable();
            $table->boolean('cyanosis')->nullable();
            $table->boolean('edema_feet')->nullable();
            $table->boolean('dehydration')->nullable();
            $table->boolean('lymphadenopathy')->nullable();
            $table->boolean('peripheral_pulses')->nullable();
            $table->boolean('feet_ulcer')->nullable();

            $table->unsignedBigInteger('country_id')->nullable(); // FK to be added later
            $table->string('gov_id')->nullable(); // FK to be added later, or just text

            $table->string('prescription_notes')->nullable();
            $table->text('address')->nullable();
            $table->string('heart_rate')->nullable();
            $table->string('spo2')->nullable();
            $table->double('discount')->default(0.0); // Or decimal('discount', 8, 2)
            $table->text('drug_history')->default('');
            $table->text('family_history')->default('');
            $table->string('rbs')->default('');
            $table->boolean('doctor_finish')->default(false);
            $table->text('care_plan')->default('');
            $table->boolean('doctor_lab_request_confirm')->default(false);
            $table->boolean('doctor_lab_urgent_confirm')->default(false);

            // Examination notes - these are all strings, assuming short notes
            $table->string('general_examination_notes')->default(''); // Added default based on others
            $table->string('patient_medical_history')->default(''); // Added default
            $table->string('social_history')->default(''); // Added default
            $table->string('allergies')->default(''); // Added default
            $table->string('general')->default(''); // Added default
            $table->string('skin')->default(''); // Added default
            $table->string('head')->default(''); // Added default
            $table->string('eyes')->default(''); // Added default
            $table->string('ear')->default(''); // Added default
            $table->string('nose')->default(''); // Added default
            $table->string('mouth')->default(''); // Added default
            $table->string('throat')->default(''); // Added default
            $table->string('neck')->default(''); // Added default
            $table->string('respiratory_system')->default(''); // Added default
            $table->string('cardio_system')->default(''); // Added default
            $table->string('git_system')->default(''); // Added default
            $table->string('genitourinary_system')->default(''); // Added default
            $table->string('nervous_system')->default(''); // Added default
            $table->string('musculoskeletal_system')->default(''); // Added default
            $table->string('neuropsychiatric_system')->default(''); // Added default
            $table->string('endocrine_system')->default(''); // Added default
            $table->string('peripheral_vascular_system')->default(''); // Added default
            $table->string('referred')->default(''); // Added default
            $table->string('discount_comment')->default(''); // Added default

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};