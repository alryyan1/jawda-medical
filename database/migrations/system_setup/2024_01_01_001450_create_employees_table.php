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
        Schema::create('employees', function (Blueprint $table) {
            $table->id('id');
            $table->string('name', 255);
            $table->integer('age');
            $table->text('phone');
            $table->text('email');
            $table->double('salary');
            $table->boolean('is_manager')->default(0);
            $table->text('job_position')->nullable();
            $table->date('first_contract_date')->nullable();
            $table->text('working_hours')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->longText('resume')->nullable();
            $table->text('address')->nullable();
            $table->text('language')->nullable();
            $table->text('home_work_distance')->nullable();
            $table->text('martial_status')->nullable();
            $table->integer('number_of_children')->nullable();
            $table->text('emergency_contact_name')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->unsignedBigInteger('country_id');
            $table->string('passport_no', 255)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth', 255)->nullable();
            $table->boolean('non_resident')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('department_id', 'employees_department_id_foreign')
                  ->references('id')
                  ->on('departments')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'employees_user_id_foreign')
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
        Schema::dropIfExists('employees');
    }
};
