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
            // Snapshot values written once when the shift is closed.
            $table->unsignedInteger('snap_patients_count')->nullable()->after('end_time');

            // Revenue totals
            $table->decimal('snap_total_paid',              12, 2)->nullable()->after('snap_patients_count');
            $table->decimal('snap_total_cash_revenue',      12, 2)->nullable()->after('snap_total_paid');
            $table->decimal('snap_total_insurance_revenue', 12, 2)->nullable()->after('snap_total_cash_revenue');
            $table->decimal('snap_total_bank',              12, 2)->nullable()->after('snap_total_insurance_revenue');

            // Doctor percentages captured at close time
            $table->decimal('snap_doctor_cash_percentage',      5, 2)->nullable()->after('snap_total_bank');
            $table->decimal('snap_doctor_insurance_percentage', 5, 2)->nullable()->after('snap_doctor_cash_percentage');

            // Doctor entitlement breakdown
            $table->decimal('snap_doctor_cash_entitlement',      12, 2)->nullable()->after('snap_doctor_insurance_percentage');
            $table->decimal('snap_doctor_insurance_entitlement', 12, 2)->nullable()->after('snap_doctor_cash_entitlement');
            $table->decimal('snap_doctor_fixed_entitlement',     12, 2)->nullable()->after('snap_doctor_insurance_entitlement');
            $table->decimal('snap_total_doctor_entitlement',     12, 2)->nullable()->after('snap_doctor_fixed_entitlement');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'snap_patients_count',
                'snap_total_paid',
                'snap_total_cash_revenue',
                'snap_total_insurance_revenue',
                'snap_total_bank',
                'snap_doctor_cash_percentage',
                'snap_doctor_insurance_percentage',
                'snap_doctor_cash_entitlement',
                'snap_doctor_insurance_entitlement',
                'snap_doctor_fixed_entitlement',
                'snap_total_doctor_entitlement',
            ]);
        });
    }
};
