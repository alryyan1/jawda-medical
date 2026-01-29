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
        Schema::create('patients', function (Blueprint $table) {
            $table->id('id');
            $table->string('lab_to_lab_object_id', 255)->nullable();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->string('name', 255);
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('specialist_doctor_id')->nullable();
            $table->string('phone', 10);
            $table->string('lab_to_lab_id', 255)->nullable();
            $table->string('gender', 255);
            $table->date('dob')->nullable();
            $table->enum('social_status', ["single","married","widowed","divorced"])->nullable();
            $table->integer('age_day')->nullable();
            $table->integer('age_month')->nullable();
            $table->integer('age_year')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('subcompany_id')->nullable();
            $table->unsignedBigInteger('company_relation_id')->nullable();
            $table->integer('paper_fees')->nullable();
            $table->string('guarantor', 255)->nullable();
            $table->date('expire_date')->nullable();
            $table->string('insurance_no', 255)->nullable();
            $table->boolean('is_lab_paid')->default(0);
            $table->integer('lab_paid')->default(0);
            $table->boolean('result_is_locked')->default(0);
            $table->boolean('sample_collected')->default(0);
            $table->time('sample_collect_time')->nullable();
            $table->dateTime('result_print_date')->nullable();
            $table->text('result_url')->nullable();
            $table->dateTime('sample_print_date')->nullable();
            $table->integer('visit_number');
            $table->boolean('result_auth');
            $table->unsignedBigInteger('result_auth_user')->nullable();
            $table->timestamp('auth_date')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('gov_id', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('income_source', 255)->nullable();
            $table->double('discount')->default(0);
            $table->boolean('doctor_finish')->default(0);
            $table->boolean('doctor_lab_request_confirm')->default(0);
            $table->boolean('doctor_lab_urgent_confirm')->default(0);
            $table->string('referred', 255)->nullable();
            $table->string('discount_comment', 255)->nullable();
            $table->unsignedBigInteger('sample_collected_by')->nullable();
            $table->foreign('company_id', 'patients_company_id_foreign')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
            $table->foreign('company_relation_id', 'patients_company_relation_id_foreign')
                  ->references('id')
                  ->on('company_relations')
                  ->onDelete('cascade');
            $table->foreign('doctor_id', 'patients_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('sample_collected_by', 'patients_sample_collected_by_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->foreign('shift_id', 'patients_shift_id_foreign')
                  ->references('id')
                  ->on('shifts')
                  ->onDelete('cascade');
            $table->foreign('specialist_doctor_id', 'patients_specialist_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('subcompany_id', 'patients_subcompany_id_foreign')
                  ->references('id')
                  ->on('subcompanies')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'patients_user_id_foreign')
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
        Schema::dropIfExists('patients');
    }
};
