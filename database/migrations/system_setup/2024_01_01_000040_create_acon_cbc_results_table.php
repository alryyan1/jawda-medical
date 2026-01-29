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
        Schema::create('acon_cbc_results', function (Blueprint $table) {
            $table->id('id');
            $table->string('patient_id', 255)->nullable();
            $table->string('patient_name', 255)->nullable();
            $table->date('patient_dob')->nullable();
            $table->string('patient_gender', 255)->nullable();
            $table->string('device_type', 255)->default('ACON');
            $table->dateTime('test_date');
            $table->longText('results');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acon_cbc_results');
    }
};
