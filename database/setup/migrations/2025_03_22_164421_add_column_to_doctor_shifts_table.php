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
            $table->boolean('is_cash_revenue_prooved')->default(false);
            $table->boolean('is_cash_reclaim_prooved')->default(false);
            $table->boolean('is_company_revenue_prooved')->default(false);
            $table->boolean('is_company_reclaim_prooved')->default(false);
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
