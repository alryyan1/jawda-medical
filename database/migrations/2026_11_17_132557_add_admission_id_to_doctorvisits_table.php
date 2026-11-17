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
        Schema::table('doctorvisits', function (Blueprint $table) {
            // No FK constraint: admissions live in the separate jawda-inpatient app's database.
            $table->unsignedBigInteger('admission_id')->nullable()->after('file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctorvisits', function (Blueprint $table) {
            $table->dropColumn('admission_id');
        });
    }
};
