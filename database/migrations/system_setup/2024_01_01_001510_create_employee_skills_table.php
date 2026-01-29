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
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('skill_id');
            $table->foreign('employee_id', 'employee_skills_employee_id_foreign')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('cascade');
            $table->foreign('skill_id', 'employee_skills_skill_id_foreign')
                  ->references('id')
                  ->on('skills')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
