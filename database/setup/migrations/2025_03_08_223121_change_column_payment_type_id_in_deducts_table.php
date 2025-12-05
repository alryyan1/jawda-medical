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
        Schema::table('doctor_shifts', function (Blueprint $table) {
            $table->boolean('is_cash_revenue_journal_generated')->default(false);
            $table->boolean('is_insurance_revenue_journal_generated')->default(false);
            $table->boolean('is_doctor_cash_reclaim_journal_generated')->default(false);
            $table->boolean('is_doctor_insurance_reclaim_journal_generated')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_shifts', function (Blueprint $table) {
            //
        });
    }
};
