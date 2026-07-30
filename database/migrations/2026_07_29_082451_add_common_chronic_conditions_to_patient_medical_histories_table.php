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
        Schema::table('patient_medical_histories', function (Blueprint $table) {
            $table->boolean('chronic_hypertension')->nullable()->after('chronic_feet_ulcer_history');
            $table->boolean('chronic_diabetes')->nullable()->after('chronic_hypertension');
            $table->boolean('chronic_heart_disease')->nullable()->after('chronic_diabetes');
            $table->boolean('chronic_ibs')->nullable()->after('chronic_heart_disease');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_medical_histories', function (Blueprint $table) {
            $table->dropColumn(['chronic_hypertension', 'chronic_diabetes', 'chronic_heart_disease', 'chronic_ibs']);
        });
    }
};
