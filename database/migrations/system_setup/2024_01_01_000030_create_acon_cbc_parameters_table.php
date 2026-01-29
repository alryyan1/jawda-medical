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
        Schema::create('acon_cbc_parameters', function (Blueprint $table) {
            $table->id('id');
            $table->string('patient_id', 255)->nullable();
            $table->string('parameter_name', 255);
            $table->string('test_code', 255);
            $table->string('test_name', 255);
            $table->string('value', 255);
            $table->string('unit', 255)->nullable();
            $table->string('reference_range', 255)->nullable();
            $table->string('abnormal_flag', 255)->nullable();
            $table->string('status', 255)->default('F');
            $table->dateTime('test_date');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acon_cbc_parameters');
    }
};
