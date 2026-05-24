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
        // Create ACON CBC results table
        Schema::create('acon_cbc_results', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id')->nullable();
            $table->string('patient_name')->nullable();
            $table->date('patient_dob')->nullable();
            $table->string('patient_gender')->nullable();
            $table->string('device_type')->default('ACON');
            $table->datetime('test_date');
            $table->json('results'); // Store all CBC results as JSON
            $table->timestamps();
            
            $table->index(['patient_id', 'test_date']);
            $table->index('test_date');
        });

        // Create ACON CBC parameters table for individual parameter storage
        Schema::create('acon_cbc_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id')->nullable();
            $table->string('parameter_name'); // WBC, RBC, HGB, etc.
            $table->string('test_code'); // LOINC code
            $table->string('test_name'); // Human readable name
            $table->string('value'); // Test result value
            $table->string('unit')->nullable(); // Unit of measurement
            $table->string('reference_range')->nullable(); // Normal range
            $table->string('abnormal_flag')->nullable(); // H, L, or normal
            $table->string('status')->default('F'); // Final, Preliminary, etc.
            $table->datetime('test_date');
            $table->timestamps();
            
            $table->index(['patient_id', 'test_date']);
            $table->index(['parameter_name', 'test_date']);
            $table->index('test_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acon_cbc_parameters');
        Schema::dropIfExists('acon_cbc_results');
    }
};
